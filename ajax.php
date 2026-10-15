<?php

require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

function json_out($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    json_out(['error' => 'Not logged in.'], 401);
}

$current_user = get_current_user_data($conn);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {


    case 'notification_counts': {
        $uid = (int) $current_user['user_id'];
        $unread = 0;
        $pending = 0;

        if ($current_user['verification_status'] === 'verified' && $current_user['role'] !== 'admin') {
            $s1 = $conn->prepare("SELECT COUNT(*) AS c FROM message WHERE receiver_id = ? AND is_read = 0");
            $s1->bind_param("i", $uid);
            $s1->execute();
            $unread = (int) $s1->get_result()->fetch_assoc()['c'];

            $s2 = $conn->prepare("SELECT COUNT(*) AS c FROM purchase_request WHERE seller_id = ? AND status = 'pending'");
            $s2->bind_param("i", $uid);
            $s2->execute();
            $pending = (int) $s2->get_result()->fetch_assoc()['c'];
        }

        json_out(['unread_messages' => $unread, 'pending_requests' => $pending]);
    }

    // ---- Fetch messages newer than a given id, mark them read ---------
    case 'get_messages': {
        $current_user = require_non_admin(require_verified_user($conn));
        $uid = (int) $current_user['user_id'];

        $listing_id = (int) ($_GET['listing_id'] ?? 0);
        $other_user_id = (int) ($_GET['other_user_id'] ?? 0);
        $after_id = (int) ($_GET['after_id'] ?? 0);

        if ($listing_id <= 0 || $other_user_id <= 0) {
            json_out(['error' => 'Missing listing_id or other_user_id.'], 400);
        }

        $stmt = $conn->prepare(
            "SELECT m.message_id, m.sender_id, m.message_body, m.sent_at, u.full_name AS sender_name
             FROM message m
             JOIN user u ON m.sender_id = u.user_id
             WHERE m.listing_id = ?
             AND m.message_id > ?
             AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
             ORDER BY m.sent_at ASC"
        );
        $stmt->bind_param("iiiiii", $listing_id, $after_id, $uid, $other_user_id, $other_user_id, $uid);
        $stmt->execute();
        $result = $stmt->get_result();

        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'id' => (int) $row['message_id'],
                'sender_id' => (int) $row['sender_id'],
                'sender_name' => $row['sender_name'],
                'message_body' => $row['message_body'],
                'sent_at' => $row['sent_at'],
                'is_mine' => (int) $row['sender_id'] === $uid,
            ];
        }

        // Mark anything from the other person as read now that it's been
        // fetched into an open conversation window.
        $read_stmt = $conn->prepare("UPDATE message SET is_read = 1 WHERE listing_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = 0");
        $read_stmt->bind_param("iii", $listing_id, $other_user_id, $uid);
        $read_stmt->execute();

        json_out(['messages' => $messages]);
    }

    // ---- Send a message -------------------------------------------------
    case 'send_message': {
        csrf_verify_ajax();
        $current_user = require_non_admin(require_verified_user($conn));
        $uid = (int) $current_user['user_id'];

        $listing_id = (int) ($_POST['listing_id'] ?? 0);
        $receiver_id = (int) ($_POST['receiver_id'] ?? 0);
        $message_body = trim($_POST['message_body'] ?? '');

        if ($listing_id <= 0 || $receiver_id <= 0 || $message_body === '') {
            json_out(['error' => 'Message cannot be empty.'], 400);
        }
        if ($receiver_id === $uid) {
            json_out(['error' => "You can't message yourself."], 400);
        }

        $listing_check = $conn->prepare("SELECT listing_id FROM listing WHERE listing_id = ?");
        $listing_check->bind_param("i", $listing_id);
        $listing_check->execute();
        if ($listing_check->get_result()->num_rows !== 1) {
            json_out(['error' => 'Listing not found.'], 404);
        }

        $stmt = $conn->prepare("INSERT INTO message (sender_id, receiver_id, listing_id, message_body) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $uid, $receiver_id, $listing_id, $message_body);

        if (!$stmt->execute()) {
            json_out(['error' => 'Failed to send message.'], 500);
        }

        json_out([
            'message' => [
                'id' => $conn->insert_id,
                'sender_id' => $uid,
                'sender_name' => $current_user['full_name'],
                'message_body' => $message_body,
                'sent_at' => date('Y-m-d H:i:s'),
                'is_mine' => true,
            ],
        ]);
    }

    // ---- Approve / reject a purchase request ---------------------------
    case 'approve_request':
    case 'reject_request': {
        csrf_verify_ajax();
        $current_user = require_non_admin(require_verified_user($conn));
        $uid = (int) $current_user['user_id'];

        $request_id = (int) ($_POST['request_id'] ?? 0);

        $rstmt = $conn->prepare("SELECT request_id, listing_id, status FROM purchase_request WHERE request_id = ? AND seller_id = ?");
        $rstmt->bind_param("ii", $request_id, $uid);
        $rstmt->execute();
        $rresult = $rstmt->get_result();

        if ($rresult->num_rows !== 1) {
            json_out(['error' => 'Request not found.'], 404);
        }
        $req = $rresult->fetch_assoc();

        if ($req['status'] !== 'pending') {
            json_out(['error' => 'This request has already been decided.'], 409);
        }

        $auto_rejected = [];

        if ($action === 'approve_request') {
            $conn->begin_transaction();
            try {
                $upd = $conn->prepare("UPDATE purchase_request SET status = 'approved' WHERE request_id = ?");
                $upd->bind_param("i", $request_id);
                $upd->execute();

                $updListing = $conn->prepare("UPDATE listing SET status = 'sold' WHERE listing_id = ?");
                $updListing->bind_param("i", $req['listing_id']);
                $updListing->execute();

                // Collect which sibling requests are about to be auto-rejected
                // so the frontend can update those rows too.
                $find_siblings = $conn->prepare("SELECT request_id FROM purchase_request WHERE listing_id = ? AND request_id != ? AND status = 'pending'");
                $find_siblings->bind_param("ii", $req['listing_id'], $request_id);
                $find_siblings->execute();
                $sib_result = $find_siblings->get_result();
                while ($sib = $sib_result->fetch_assoc()) {
                    $auto_rejected[] = (int) $sib['request_id'];
                }

                $rejectOthers = $conn->prepare("UPDATE purchase_request SET status = 'rejected' WHERE listing_id = ? AND request_id != ? AND status = 'pending'");
                $rejectOthers->bind_param("ii", $req['listing_id'], $request_id);
                $rejectOthers->execute();

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                json_out(['error' => 'Something went wrong approving this request.'], 500);
            }

            json_out(['status' => 'approved', 'request_id' => $request_id, 'auto_rejected' => $auto_rejected]);

        } else {
            $upd = $conn->prepare("UPDATE purchase_request SET status = 'rejected' WHERE request_id = ?");
            $upd->bind_param("i", $request_id);
            $upd->execute();

            json_out(['status' => 'rejected', 'request_id' => $request_id]);
        }
    }

    default:
        json_out(['error' => 'Unknown action.'], 400);
}