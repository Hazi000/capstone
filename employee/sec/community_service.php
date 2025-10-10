<?php
session_start();
require_once '../../config.php';

// Set PHP timezone to Philippine time and set MySQL session timezone
date_default_timezone_set('Asia/Manila');
@mysqli_query($connection, "SET time_zone = '+08:00'");

// --- NEW: ensure volunteer_proofs table exists (stores uploaded proof images) ---
$create_proofs_table_sql = "
CREATE TABLE IF NOT EXISTS volunteer_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (registration_id),
    CONSTRAINT fk_vp_registration FOREIGN KEY (registration_id) 
        REFERENCES volunteer_registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
@mysqli_query($connection, $create_proofs_table_sql); // suppress errors here; adjust error handling as needed

// --- NEW: ensure volunteer_proofs has a status column (pending/approved/rejected) ---
$vp_cols_res = @mysqli_query($connection, "DESCRIBE `volunteer_proofs`");
$vp_cols = [];
if ($vp_cols_res) {
    while ($c = mysqli_fetch_assoc($vp_cols_res)) {
        $vp_cols[] = $c['Field'];
    }
    mysqli_free_result($vp_cols_res);
}
if (!in_array('status', $vp_cols)) {
    @mysqli_query($connection, "ALTER TABLE `volunteer_proofs` ADD COLUMN `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
}

// --- NEW: ensure volunteer_registrations.status has required ENUM values and default ---
// This avoids blank/NULL status when approvals happen.
$vr_cols_res = @mysqli_query($connection, "DESCRIBE `volunteer_registrations`");
$vr_cols = [];
if ($vr_cols_res) {
    while ($c = mysqli_fetch_assoc($vr_cols_res)) {
        $vr_cols[] = $c['Field'];
    }
    mysqli_free_result($vr_cols_res);
}
if (!in_array('status', $vr_cols)) {
    @mysqli_query($connection, "ALTER TABLE `volunteer_registrations` ADD COLUMN `status` ENUM('pending','approved','rejected','attended') NOT NULL DEFAULT 'pending'");
} else {
    // Modify column to ensure it contains needed values and non-null default (no harm if already correct)
    @mysqli_query($connection, "ALTER TABLE `volunteer_registrations` MODIFY COLUMN `status` ENUM('pending','approved','rejected','attended') NOT NULL DEFAULT 'pending'");
}

// Check if user is logged in and is a secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: ../index.php");
    exit();
}

// Handle POST actions for approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering to capture any accidental output (warnings, notices, whitespace)
    // so we can ensure the response is valid JSON for AJAX requests.
    ob_start();

    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_event':
                $title = mysqli_real_escape_string($connection, $_POST['title']);
                $description = mysqli_real_escape_string($connection, $_POST['description']);
                $start_date = mysqli_real_escape_string($connection, $_POST['event_start_date']);
                $end_date = mysqli_real_escape_string($connection, $_POST['event_end_date']);
                $event_time = mysqli_real_escape_string($connection, $_POST['event_time']);
                $location = mysqli_real_escape_string($connection, $_POST['location']);
                $max_volunteers = intval($_POST['max_volunteers']);
                
                $sql = "INSERT INTO events (title, description, event_start_date, event_end_date, 
                        event_time, location, max_volunteers, status, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'upcoming', ?)";
                
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssssssii", 
                    $title, $description, $start_date, $end_date, 
                    $event_time, $location, $max_volunteers, $_SESSION['user_id']
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $response = ['success' => true, 'message' => 'Event created successfully'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to create event: ' . mysqli_error($connection)];
                }
                break;

            case 'update_event':
                $id = intval($_POST['event_id']);
                $title = mysqli_real_escape_string($connection, $_POST['title']);
                $description = mysqli_real_escape_string($connection, $_POST['description']);
                $start_date = mysqli_real_escape_string($connection, $_POST['event_start_date']);
                $end_date = mysqli_real_escape_string($connection, $_POST['event_end_date']);
                $event_time = mysqli_real_escape_string($connection, $_POST['event_time']);
                $location = mysqli_real_escape_string($connection, $_POST['location']);
                $max_volunteers = intval($_POST['max_volunteers']);
                $status = mysqli_real_escape_string($connection, $_POST['status']);
                
                $sql = "UPDATE events SET title=?, description=?, event_start_date=?, 
                        event_end_date=?, event_time=?, location=?, max_volunteers=?, 
                        status=?, updated_at=NOW() WHERE id=?";
                
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssssssssi", 
                    $title, $description, $start_date, $end_date, 
                    $event_time, $location, $max_volunteers, $status, $id
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $response = ['success' => true, 'message' => 'Event updated successfully'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to update event: ' . mysqli_error($connection)];
                }
                break;

            case 'approve':
                $volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;
                
                if ($volunteer_id > 0) {
                    $sql = "UPDATE volunteer_registrations SET status = 'approved' WHERE id = ?";
                    $stmt = mysqli_prepare($connection, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Delete any uploaded proofs for this registration (file + DB row)
                        $sel = "SELECT id, file_path FROM volunteer_proofs WHERE registration_id = ?";
                        $sel_st = @mysqli_prepare($connection, $sel);
                        if ($sel_st) {
                            mysqli_stmt_bind_param($sel_st, "i", $volunteer_id);
                            mysqli_stmt_execute($sel_st);
                            $res = mysqli_stmt_get_result($sel_st);
                            while ($prow = mysqli_fetch_assoc($res)) {
                                $pf_id = intval($prow['id']);
                                $fp = $prow['file_path'] ?? '';
                                $full = __DIR__ . '/../../' . ltrim($fp, '/');
                                if ($fp && file_exists($full)) {
                                    @unlink($full);
                                }
                                // delete db row
                                $del = "DELETE FROM volunteer_proofs WHERE id = ?";
                                $del_st = @mysqli_prepare($connection, $del);
                                if ($del_st) {
                                    mysqli_stmt_bind_param($del_st, "i", $pf_id);
                                    @mysqli_stmt_execute($del_st);
                                    @mysqli_stmt_close($del_st);
                                } else {
                                    @mysqli_query($connection, "DELETE FROM volunteer_proofs WHERE id = ".intval($pf_id));
                                }
                            }
                            @mysqli_stmt_close($sel_st);
                        } else {
                            // fallback: best-effort delete via single query (files cannot be deleted without select)
                            @mysqli_query($connection, "DELETE FROM volunteer_proofs WHERE registration_id = ".intval($volunteer_id));
                        }

                        $response = ['success' => true, 'message' => 'Volunteer approved successfully'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to approve volunteer'];
                    }
                }
                break;

            // NEW: approve a proof record and ensure the corresponding registration becomes 'approved'
            case 'approve_proof':
                $proof_id = isset($_POST['proof_id']) ? intval($_POST['proof_id']) : 0;
                if ($proof_id > 0) {
                    // fetch registration_id and file_path for this proof
                    $q = "SELECT registration_id, file_path FROM volunteer_proofs WHERE id = ? LIMIT 1";
                    $qstmt = mysqli_prepare($connection, $q);
                    if ($qstmt) {
                        mysqli_stmt_bind_param($qstmt, "i", $proof_id);
                        mysqli_stmt_execute($qstmt);
                        $qres = mysqli_stmt_get_result($qstmt);
                        $prow = $qres ? mysqli_fetch_assoc($qres) : null;
                        mysqli_stmt_close($qstmt);
                        $reg_id = intval($prow['registration_id'] ?? 0);
                        $file_path = $prow['file_path'] ?? '';
                    } else {
                        $reg_id = 0;
                        $file_path = '';
                    }
                    
                    // delete file and proof row
                    $deleted_ok = false;
                    if ($file_path) {
                        $full = __DIR__ . '/../../' . ltrim($file_path, '/');
                        if (file_exists($full)) { @unlink($full); }
                    }
                    $del = "DELETE FROM volunteer_proofs WHERE id = ?";
                    $delst = @mysqli_prepare($connection, $del);
                    if ($delst) {
                        mysqli_stmt_bind_param($delst, "i", $proof_id);
                        $deleted_ok = mysqli_stmt_execute($delst);
                        mysqli_stmt_close($delst);
                    } else {
                        $deleted_ok = @mysqli_query($connection, "DELETE FROM volunteer_proofs WHERE id = ".intval($proof_id));
                    }
                    
                    // ensure registration is approved as well (if we found it)
                    $ok2 = true;
                    if ($reg_id > 0) {
                        $ur = "UPDATE volunteer_registrations SET status = 'approved' WHERE id = ?";
                        $urst = mysqli_prepare($connection, $ur);
                        if ($urst) {
                            mysqli_stmt_bind_param($urst, "i", $reg_id);
                            $ok2 = mysqli_stmt_execute($urst);
                            mysqli_stmt_close($urst);
                        } else {
                            $ok2 = @mysqli_query($connection, "UPDATE volunteer_registrations SET status = 'approved' WHERE id = ".intval($reg_id));
                        }
                    }
                    
                    if ($deleted_ok && $ok2) {
                        $response = ['success' => true, 'message' => 'Proof approved and registration updated (proof removed)'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to approve proof or update registration'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Invalid proof id'];
                }
                break;
            
            case 'reject':
                $volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;
                $reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
                if ($volunteer_id > 0 && !empty($reason)) {
                    $sql = "UPDATE volunteer_registrations SET status = 'rejected', rejection_reason = ? WHERE id = ?";
                    $stmt = mysqli_prepare($connection, $sql);
                    mysqli_stmt_bind_param($stmt, "si", $reason, $volunteer_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Delete proofs for this registration (file + DB row)
                        $sel = "SELECT id, file_path FROM volunteer_proofs WHERE registration_id = ?";
                        $sel_st = @mysqli_prepare($connection, $sel);
                        if ($sel_st) {
                            mysqli_stmt_bind_param($sel_st, "i", $volunteer_id);
                            mysqli_stmt_execute($sel_st);
                            $res = mysqli_stmt_get_result($sel_st);
                            while ($prow = mysqli_fetch_assoc($res)) {
                                $pf_id = intval($prow['id']);
                                $fp = $prow['file_path'] ?? '';
                                $full = __DIR__ . '/../../' . ltrim($fp, '/');
                                if ($fp && file_exists($full)) {
                                    @unlink($full);
                                }
                                $del = "DELETE FROM volunteer_proofs WHERE id = ?";
                                $del_st = @mysqli_prepare($connection, $del);
                                if ($del_st) {
                                    mysqli_stmt_bind_param($del_st, "i", $pf_id);
                                    @mysqli_stmt_execute($del_st);
                                    @mysqli_stmt_close($del_st);
                                } else {
                                    @mysqli_query($connection, "DELETE FROM volunteer_proofs WHERE id = ".intval($pf_id));
                                }
                            }
                            @mysqli_stmt_close($sel_st);
                        } else {
                            @mysqli_query($connection, "DELETE FROM volunteer_proofs WHERE registration_id = ".intval($volunteer_id));
                        }

                        $response = ['success' => true, 'message' => 'Volunteer rejected successfully'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to reject volunteer'];
                    }
                } else if (empty($reason)) {
                    $response = ['success' => false, 'message' => 'Rejection reason is required'];
                }
                break;

            case 'mark_attended':
                // Mark volunteer as attended (confirmation only). Set attended_at = NOW()
                $volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;
                if ($volunteer_id > 0) {
                    $sql = "UPDATE volunteer_registrations SET status = 'attended', attended_at = NOW() WHERE id = ?";
                    $stmt = mysqli_prepare($connection, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);

                    if (mysqli_stmt_execute($stmt)) {
                        // Delete proofs for this registration (file + DB row) instead of marking approved
                        $sel = "SELECT id, file_path FROM volunteer_proofs WHERE registration_id = ?";
                        $sel_st = @mysqli_prepare($connection, $sel);
                        if ($sel_st) {
                            mysqli_stmt_bind_param($sel_st, "i", $volunteer_id);
                            mysqli_stmt_execute($sel_st);
                            $res = mysqli_stmt_get_result($sel_st);
                            while ($prow = mysqli_fetch_assoc($res)) {
                                $pf_id = intval($prow['id']);
                                $fp = $prow['file_path'] ?? '';
                                $full = __DIR__ . '/../../' . ltrim($fp, '/');
                                if ($fp && file_exists($full)) {
                                    @unlink($full);
                                }
                                $del = "DELETE FROM volunteer_proofs WHERE id = ?";
                                $del_st = @mysqli_prepare($connection, $del);
                                if ($del_st) {
                                    mysqli_stmt_bind_param($del_st, "i", $pf_id);
                                    @mysqli_stmt_execute($del_st);
                                    @mysqli_stmt_close($del_st);
                                } else {
                                    @mysqli_query($connection, "DELETE FROM volunteer_proofs WHERE id = ".intval($pf_id));
                                }
                            }
                            @mysqli_stmt_close($sel_st);
                        } else {
                            @mysqli_query($connection, "DELETE FROM volunteer_proofs WHERE registration_id = ".intval($volunteer_id));
                        }

                        $response = ['success' => true, 'message' => 'Attendance confirmed and proofs removed'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to confirm attendance: ' . mysqli_error($connection)];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Invalid volunteer'];
                }
                break;

        }
    }
    
    // Clean any buffered output before returning JSON to avoid invalid JSON responses
    if (ob_get_length() !== false) {
        @ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- UPDATED: AJAX endpoint to fetch volunteer proof and volunteer info ---
// Called via GET: community_service.php?volunteer_proof=123
if (isset($_GET['volunteer_proof'])) {
    // buffer any accidental output and clean before sending JSON
    ob_start();

    $vid = intval($_GET['volunteer_proof']);
    if ($vid <= 0) {
        if (ob_get_length() !== false) { @ob_end_clean(); }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid id']);
        exit;
    }
    // Attempt to fetch latest proof file_path from volunteer_proofs and resident info
    $q = "SELECT vp.file_path AS proof_path, r.full_name 
          FROM volunteer_registrations vr
          LEFT JOIN residents r ON vr.resident_id = r.id
          LEFT JOIN volunteer_proofs vp ON vp.registration_id = vr.id
          WHERE vr.id = ?
          ORDER BY vp.uploaded_at DESC
          LIMIT 1";
    $stmt = mysqli_prepare($connection, $q);
    mysqli_stmt_bind_param($stmt, "i", $vid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        if (ob_get_length() !== false) { @ob_end_clean(); }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'proof_path' => $row['proof_path'] ?? null,
            'full_name' => $row['full_name'] ?? ''
        ]);
    } else {
        // no proof found - still attempt to return resident name if possible
        $rq = "SELECT r.full_name FROM volunteer_registrations vr
               LEFT JOIN residents r ON vr.resident_id = r.id
               WHERE vr.id = ? LIMIT 1";
        $rstmt = mysqli_prepare($connection, $rq);
        mysqli_stmt_bind_param($rstmt, "i", $vid);
        mysqli_stmt_execute($rstmt);
        $rres = mysqli_stmt_get_result($rstmt);
        $fname = '';
        if ($rres && $rrow = mysqli_fetch_assoc($rres)) {
            $fname = $rrow['full_name'];
        }
        if (ob_get_length() !== false) { @ob_end_clean(); }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not found', 'full_name' => $fname]);
    }
    exit;
}

// Get event ID if viewing requests
$event_id = isset($_GET['view_requests']) ? intval($_GET['view_requests']) : 0;
$pending_volunteers = [];
$view_event_title = ''; // hold title when viewing requests

// If viewing requests, get the pending volunteers (case-insensitive status and left join residents)
if ($event_id > 0) {
    $sql = "SELECT vr.id, vr.registration_date, COALESCE(r.full_name,'Unknown') AS full_name, r.contact_number, r.email
            FROM volunteer_registrations vr
            LEFT JOIN residents r ON vr.resident_id = r.id
            WHERE vr.event_id = ? AND LOWER(COALESCE(vr.status,'')) = 'pending'
            ORDER BY vr.registration_date ASC";
    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $event_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $pending_volunteers[] = $row;
        }
    } else {
        // fallback to simple query if prepare fails
        $fallback = mysqli_query($connection, "SELECT vr.id, vr.registration_date, COALESCE(r.full_name,'Unknown') AS full_name, r.contact_number, r.email FROM volunteer_registrations vr LEFT JOIN residents r ON vr.resident_id = r.id WHERE vr.event_id = ".intval($event_id)." AND LOWER(COALESCE(vr.status,'')) = 'pending' ORDER BY vr.registration_date ASC");
        if ($fallback) {
            while ($row = mysqli_fetch_assoc($fallback)) {
                $pending_volunteers[] = $row;
            }
        }
    }

    // fetch event title for header
    $et_sql = "SELECT title FROM events WHERE id = ? LIMIT 1";
    $et_stmt = mysqli_prepare($connection, $et_sql);
    if ($et_stmt) {
        mysqli_stmt_bind_param($et_stmt, "i", $event_id);
        mysqli_stmt_execute($et_stmt);
        $et_res = mysqli_stmt_get_result($et_stmt);
        if ($et_res && $erow = mysqli_fetch_assoc($et_res)) {
            $view_event_title = $erow['title'];
        }
    }
}

// Get dashboard statistics for sidebar
$stats = [];

// Get complaints statistics
$complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $complaint_query);
$stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];

// Get volunteer statistics
$volunteer_stats = [];
$volunteer_stats_query = "SELECT 
    (SELECT COUNT(*) FROM events WHERE status IN ('upcoming', 'ongoing')) as active_events,
    (SELECT COUNT(DISTINCT resident_id) FROM volunteer_registrations WHERE LOWER(COALESCE(status,'')) IN ('approved','attended')) as total_volunteers,
    (SELECT COUNT(*) FROM volunteer_registrations WHERE LOWER(COALESCE(status,'')) = 'pending') as pending_applications";

$volunteer_result = mysqli_query($connection, $volunteer_stats_query);
$volunteer_stats = mysqli_fetch_assoc($volunteer_result);

// Get upcoming events with pagination - Modified to exclude past events
$items_per_page = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of events for pagination - Only upcoming and ongoing events
$total_query = "SELECT COUNT(*) as total FROM events 
                WHERE status IN ('upcoming', 'ongoing') 
                AND event_start_date >= CURDATE()";
$total_result = mysqli_query($connection, $total_query);
$total_events = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_events / $items_per_page);

// Modified events query with LIMIT and OFFSET - Only upcoming and ongoing events
$events_query = "SELECT 
    e.*,
    COUNT(DISTINCT CASE WHEN LOWER(COALESCE(vr.status,'')) IN ('approved','attended') THEN vr.id ELSE NULL END) as volunteer_count,
    SUM(CASE WHEN LOWER(COALESCE(vr.status,'')) = 'pending' THEN 1 ELSE 0 END) as pending_count
FROM events e
LEFT JOIN volunteer_registrations vr ON e.id = vr.event_id
WHERE e.status IN ('upcoming', 'ongoing') 
AND e.event_start_date >= CURDATE()
GROUP BY e.id
ORDER BY e.event_start_date ASC
LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($connection, $events_query);
mysqli_stmt_bind_param($stmt, "ii", $items_per_page, $offset);
mysqli_stmt_execute($stmt);
$events_result = mysqli_stmt_get_result($stmt);

// Handle AJAX request for volunteer applications
if (isset($_GET['event_id']) && !empty($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
    
    // Get event details first
    $event_sql = "SELECT title FROM events WHERE id = ?";
    $stmt = mysqli_prepare($connection, $event_sql);
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $event_result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($event_result);
    
    // Then get the volunteer requests
    $sql = "SELECT vr.id, vr.registration_date, COALESCE(r.full_name,'Unknown') AS full_name, r.contact_number, r.email 
            FROM volunteer_registrations vr
            LEFT JOIN residents r ON vr.resident_id = r.id 
            WHERE vr.event_id = ? AND LOWER(COALESCE(vr.status,'')) LIKE '%pend%'
            ORDER BY vr.registration_date ASC";
            
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // If this is an AJAX request, return HTML response
    if (isset($_GET['ajax']) || 
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
         strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
        
        if ($result && mysqli_num_rows($result) > 0) {
            echo "<h3>Pending Requests for: " . htmlspecialchars($event['title']) . "</h3>";
            echo "<div class='table-container' style='margin-top: 1rem;'>";
            echo "<table class='table'>";
            echo "<thead>
                    <tr>
                        <th>Name</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                  </thead><tbody>";

            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['full_name'] ?? 'Unknown') . "</td>";
                echo "<td>" . date('M d, Y', strtotime($row['registration_date'])) . "</td>";
                echo "<td class='table-actions'>
                        <button class='btn btn-success btn-sm' onclick='approveVolunteer(" . $row['id'] . ")'>
                            <i class='fas fa-check'></i> Approve
                        </button>
                        <button class='btn btn-danger btn-sm' onclick='openRejectModal(" . $row['id'] . ", \"" . htmlspecialchars($row['full_name'], ENT_QUOTES) . "\")'>
                            <i class='fas fa-times'></i> Reject
                        </button>
                      </td>";
                echo "</tr>";
            }
            
            echo "</tbody></table></div>";
        } else {
            echo "<div class='empty-state' style='text-align:center; padding:2rem;'>
                    <i class='fas fa-clipboard-check' style='font-size:3rem; color:#ddd; margin-bottom:1rem;'></i>
                    <h3>No Pending Requests</h3>
                    <p>There are currently no pending volunteer applications for this event.</p>
                  </div>";
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Service - Cawit Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../css/community-service.css" rel="stylesheet">
    <!-- Ensure SweetAlert2 is loaded before any script that calls Swal -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- removed external script to avoid unexpected JSON parsing by that file -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            transition: transform 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .sidebar-brand i {
            margin-right: 12px;
            font-size: 1.5rem;
            color: #3498db;
        }

        .user-info {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }

        .user-name {
            font-weight: bold;
            font-size: 1rem;
        }

        .user-role {
            font-size: 0.85rem;
            opacity: 0.8;
            color: #3498db;
        }

        .sidebar-nav {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            padding: 0 1.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #bdc3c7;
            margin-bottom: 0.5rem;
        }

        .nav-item {
            display: block;
            padding: 1rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            border-left-color: #3498db;
        }

        .nav-item.active {
            background: rgba(52, 152, 219, 0.2);
            border-left-color: #3498db;
        }

        .nav-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .nav-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: auto;
            float: right;
        }

        /* Fixed logout section positioning */
        .logout-section {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
            margin-top: auto;
        }

        .logout-btn {
            width: 100%;
            background: rgba(231, 76, 60, 0.2);
            color: white;
            border: 1px solid rgba(231, 76, 60, 0.5);
            padding: 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.3);
            border-color: #e74c3c;
            transform: translateY(-1px);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
        }

        .page-title {
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
        }

        .content-area {
            padding: 2rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* View Tabs */
        .view-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
        }

        .view-tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #666;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-tab:hover {
            color: #333;
        }

        .view-tab.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-right: 1rem;
            width: 60px;
            text-align: center;
        }

        .stat-content h3 {
            font-size: 2rem;
            margin-bottom: 0.25rem;
            color: #333;
        }

        .stat-content p {
            color: #666;
            font-size: 0.9rem;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .event-card:hover {
            transform: translateY(-3px);
            color: white;
        }

        .event-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .event-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .event-body {
            padding: 1.5rem;
        }

        .event-info {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .event-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .event-info-item i {
            width: 20px;
            text-align: center;
            color: #3498db;
        }

        .volunteer-progress {
            margin-top: 1rem;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            color: #666;
        }

        .progress-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            background: #27ae60;
            height: 100%;
            transition: width 0.3s ease;
        }

        /* Resident Profile Card */
        .resident-profile {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .profile-info h2 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .profile-stat {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .profile-stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
        }

        .profile-stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .volunteer-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .volunteer-inactive {
            background: #e2e3e5;
            color: #383d41;
        }

        .volunteer-active {
            background: #d4edda;
            color: #155724;
        }

        .volunteer-outstanding {
            background: #ffd700;
            color: #856404;
        }

        /* Volunteers Table */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #f5f5f5;
        }

        .table tbody tr {
            transition: background 0.3s ease;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .volunteer-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .volunteer-name {
            font-weight: 600;
            color: #333;
        }

        .volunteer-contact {
            font-size: 0.85rem;
            color: #666;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .attendance-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-block;
        }

        .attendance-attended {
            background: #d1ecf1;
            color: #0c5460;
        }

        .attendance-absent {
            background: #e2e3e5;
            color: #383d41;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 400px;  /* Reduced from 500px */
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
            max-height: 85vh;  /* Added max height */
            display: flex;     /* Added flex display */
            flex-direction: column; /* Added column direction */
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
            padding: 1.2rem;
            overflow-y: auto;  /* Added scroll */
            flex: 1;          /* Added flex grow */
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            padding: 1rem;    /* Added padding */
            border-top: 1px solid #eee;
            background: #fff; /* Added background */
            border-radius: 0 0 12px 12px;
            position: sticky; /* Make buttons stick to bottom */
            bottom: 0;
        }

        /* Certificate Preview */
        .certificate-preview {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 800px;
            height: 600px;
            background: white;
            border: 20px solid #f4e4c1;
            box-shadow: 0 0 50px rgba(0,0,0,0.3);
            padding: 60px;
            text-align: center;
            z-index: 3000;
        }

        .certificate-header {
            margin-bottom: 40px;
        }

        .certificate-logo {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 20px;
        }

        .certificate-title {
            font-size: 3rem;
            color: #2c3e50;
            font-family: 'Georgia', serif;
            margin-bottom: 10px;
        }

        .certificate-subtitle {
            font-size: 1.2rem;
            color: #666;
            font-style: italic;
        }

        .certificate-body {
            margin: 40px 0;
        }

        .certificate-recipient {
            font-size: 2.5rem;
            color: #3498db;
            font-weight: bold;
            margin: 20px 0;
            font-family: 'Georgia', serif;
        }

        .certificate-text {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333;
            margin: 20px 0;
        }

        .certificate-stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
        }

        .certificate-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }

        .certificate-signature {
            text-align: center;
            flex: 1;
        }

        .signature-line {
            border-top: 2px solid #333;
            margin-top: 50px;
            padding-top: 10px;
        }

        .certificate-date {
            position: absolute;
            bottom: 30px;
            right: 60px;
            font-size: 0.9rem;
            color: #666;
        }

        .certificate-actions {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .content-area {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                min-width: 100%;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
            }

            .certificate-preview {
                width: 90%;
                height: auto;
                padding: 30px;
                border-width: 10px;
            }

            .certificate-title {
                font-size: 2rem;
            }

            .certificate-recipient {
                font-size: 1.8rem;
            }
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        @media print {
            .certificate-actions {
                display: none !important;
            }
        }

        .event-actions {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .event-actions .btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .event-actions .btn:hover {
            transform: translateY(-2px);
        }

        /* pending badge for number of pending volunteer requests */
    .pending-badge {
        display: inline-block;
        background: #fff7e6;
        color: #c2410c;
        padding: 4px 8px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.85rem;
        margin-left: 8px;
        vertical-align: middle;
    }

    /* Add this to your existing CSS */
    .btn-with-badge {
        position: relative;
        padding-right: 2.5rem;
    }

    .request-badge {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: #fff7e6;
        color: #c2410c;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: bold;
        min-width: 20px;
        text-align: center;
    }

    /* Pagination Styles */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin: 2rem 0;
}

.page-numbers {
    display: flex;
    gap: 0.5rem;
}

.page-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #fff;
    color: #333;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.page-number:hover {
    background: #e9ecef;
    color: #333;
}

.page-number.active {
    background: #3498db;
    color: #fff;
}

.pagination .btn {
    padding: 0.5rem 1rem;
}
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="fas fa-building"></i>
                Cawit Barangay Management
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role">Secretary</div>
            </div>
        </div>

        <div class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main Menu</div>
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Resident Management</div>
                <a href="resident-profiling.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    Resident Profiling
                </a>
                <a href="resident_family.php" class="nav-item">
                    <i class="fas fa-user-friends"></i>
                    Resident Family
                </a>
                <a href="resident_account.php" class="nav-item">
					<i class="fas fa-user-shield"></i>
					Resident Accounts
				</a>
                
                 
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Service Management</div>
                <a href="community_service.php" class="nav-item active">
                    <i class="fas fa-hands-helping"></i>
                    Community Service
                </a>
                <a href="announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    Announcements
                </a>
                <a href="complaints.php" class="nav-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    Complaints
                    <?php if ($stats['pending_complaints'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_complaints']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="certificates.php" class="nav-item">
                    <i class="fas fa-certificate"></i>
                    Certificates
                </a>
                 <a href="disaster_management.php" class="nav-item">
                    <i class="fas fa-house-damage"></i>
                    Disaster Management
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
            
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
        </div>

        <div class="logout-section">
            <form action="../logout.php" method="POST" id="logoutForm" style="width: 100%;">
                <button type="button" class="logout-btn" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </form>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Community Service</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <script>
                    window.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: '<?php echo addslashes($_SESSION['success_message']); ?>',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        if (window.history.replaceState) {
                            window.history.replaceState(null, null, window.location.href);
                        }
                    });
                </script>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <script>
                    window.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: '<?php echo addslashes($_SESSION['error_message']); ?>',
                            timer: 2500,
                            showConfirmButton: false
                        });
                        if (window.history.replaceState) {
                            window.history.replaceState(null, null, window.location.href);
                        }
                    });
                </script>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- View Tabs -->
            <div class="view-tabs">
                <div class="view-tab active">
                    <i class="fas fa-calendar-check"></i>
                    Community Events
                </div>
            </div>

            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #3498db;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $volunteer_stats['active_events']; ?></h3>
                        <p>Active Events</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #27ae60;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $volunteer_stats['total_volunteers'] ?? 0; ?></h3>
                        <p>Total Volunteers</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #f39c12;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $volunteer_stats['pending_applications'] ?? 0; ?></h3>
                        <p>Pending Applications</p>
                    </div>
                </div>
            </div>

            <!-- Pending Requests: shown when ?view_requests=<event_id> is present -->
            <?php if ($event_id > 0): ?>
                <div class="section-header">
                    <h2 class="section-title">Pending Requests<?php echo $view_event_title ? ' — ' . htmlspecialchars($view_event_title) : ''; ?></h2>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Back to Events</a>
                </div>

                <div class="table-container" style="margin-bottom:2rem;">
                    <?php if (!empty($pending_volunteers)): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Registration Date</th>
                                    <th>Contact / Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_volunteers as $pv): ?>
                                    <tr>
                                        <td class="volunteer-info">
                                            <div class="volunteer-name"><?php echo htmlspecialchars($pv['full_name']); ?></div>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($pv['registration_date'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($pv['contact_number']); ?><br>
                                            <small><?php echo htmlspecialchars($pv['email']); ?></small>
                                        </td>
                                        <td class="table-actions">
                                            <button class="btn btn-success btn-sm" onclick="approveVolunteer(<?php echo $pv['id']; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="openRejectModal(<?php echo $pv['id']; ?>, '<?php echo htmlspecialchars($pv['full_name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                            <button class="btn btn-info btn-sm" onclick="markAttendance(<?php echo $pv['id']; ?>, '<?php echo htmlspecialchars($pv['full_name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-image"></i> Proof / Attendance
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state" style="padding:1.5rem;">
                            <i class="fas fa-clipboard-check" style="font-size:2rem; color:#ddd;"></i>
                            <h3>No Pending Requests</h3>
                            <p>There are currently no pending volunteer applications for this event.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Upcoming Events Section -->
            <div class="section-header">
                <h2 class="section-title">Upcoming Community Events</h2>
                <button type="button" class="btn btn-primary" onclick="openEventModal('create')">
                    <i class="fas fa-plus"></i> Create New Event
                </button>
            </div>

            <div class="events-grid">
            <?php 
            if (mysqli_num_rows($events_result) > 0): 
                while ($event = mysqli_fetch_assoc($events_result)): 
                    $event_date = new DateTime($event['event_start_date']);
                    $today = new DateTime();
                    $is_past = $event_date < $today;
            ?>
                <div class="event-card">
                    <div class="event-header">
                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                        <div class="event-date">
                            <i class="fas fa-calendar"></i>
                            <?php echo $event_date->format('F j, Y'); ?>
                        </div>
                    </div>
                    <div class="event-body">
                        <div class="event-info">
                            <?php if (!empty($event['event_time'])): ?>
                                <?php $formatted_time = date('g:i A', strtotime($event['event_time'])); ?>
                                <div class="event-info-item">
                                    <i class="far fa-clock"></i> <?php echo htmlspecialchars($formatted_time); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($event['location'])): ?>
                                <div class="event-info-item">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="event-info-item">
                                <i class="fas fa-users"></i>
                                Volunteers: <?php echo intval($event['volunteer_count']); ?>
                                <?php if (!empty($event['max_volunteers'])): ?>
                                    /<?php echo intval($event['max_volunteers']); ?>
                                <?php endif; ?>
                                
                            </div>
                        </div>
                        
                        <div class="event-actions">
                            <button type="button" class="btn btn-info btn-with-badge" onclick="showVolunteerApplications(<?php echo $event['id']; ?>)">
                                <i class="fas fa-clipboard-list"></i> View Requests
                                <?php if (!empty($event['pending_count'])): ?>
                                    <span class="request-badge"><?php echo intval($event['pending_count']); ?></span>
                                <?php endif; ?>
                            </button>
                            <button type="button" class="btn btn-primary" onclick="showEventVolunteers(<?php echo $event['id']; ?>)">
                                <i class="fas fa-users"></i> View Event Volunteer
                            </button>
                        </div>
                    </div>
                </div>
            <?php 
                endwhile; 
            else: 
            ?>
                <div class="empty-state" style="grid-column: 1/-1;">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No upcoming events</h3>
                    <p>Check back later for community service opportunities.</p>
                </div>
            <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?>" class="btn btn-primary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <div class="page-numbers">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1); ?>" class="btn btn-primary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Attendance Modal -->
    <div class="modal" id="attendanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Attendance</h2>
                <button class="modal-close" onclick="closeAttendanceModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="attendanceForm">
                    <input type="hidden" name="action" value="mark_attended">
                    <input type="hidden" name="volunteer_id" id="attendanceVolunteerId">
                    
                    <p style="margin-bottom: 1rem;">
                        Confirm attendance for: <strong id="attendanceVolunteerName"></strong>
                    </p>

                    <!-- Proof image preview -->
                    <div id="proofContainer" style="margin-bottom:1rem; text-align:center; display:none;">
                        <p style="margin-bottom:0.5rem;"><small>Volunteer submitted proof:</small></p>
                        <img id="proofImage" src="" alt="Proof Image" style="max-width:100%; max-height:320px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1);" />
                    </div>
                    <div id="noProofNotice" style="margin-bottom:0.75rem; color:#666; display:none;">
                        No proof image submitted by volunteer.
                    </div>
                    
                    <!-- Confirmation only: no hours input required -->
 
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAttendanceModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Confirm Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Certificate Preview -->
    <div class="certificate-preview" id="certificatePreview">
        <div class="certificate-actions">
            <button class="btn btn-primary" onclick="printCertificate()">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="btn btn-secondary" onclick="closeCertificate()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        
        <div class="certificate-header">
            <div class="certificate-logo">
                <i class="fas fa-building"></i>
            </div>
            <h1 class="certificate-title">Certificate of Appreciation</h1>
            <p class="certificate-subtitle">For Outstanding Community Service</p>
        </div>
        
        <div class="certificate-body">
            <p class="certificate-text">This is to certify that</p>
            <h2 class="certificate-recipient" id="certRecipientName">John Doe</h2>
            <p class="certificate-text">
                has demonstrated exceptional dedication and commitment to community service
                through voluntary participation in barangay activities and events.
            </p>
            
            <div class="certificate-stats">
                <p><strong>Total Volunteer Hours:</strong> <span id="certHours">0</span> hours</p>
                <p><strong>Events Participated:</strong> <span id="certEvents">0</span> events</p>
            </div>
            
            <p class="certificate-text">
                We express our sincere gratitude for your selfless service and valuable contribution
                to the betterment of our community.
            </p>
        </div>
        
        <div class="certificate-footer">
            <div class="certificate-signature">
                <div class="signature-line">
                    <strong>Barangay Captain</strong>
                </div>
            </div>
            <div class="certificate-signature">
                <div class="signature-line">
                    <strong>Secretary</strong>
                </div>
            </div>
        </div>
        
        <div class="certificate-date">
            Issued on: <?php echo date('F j, Y'); ?>
        </div>
    </div>

    <!-- Applications Modal -->
    <div class="modal" id="applicationsModal" aria-hidden="true">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 class="modal-title">Volunteer Applications</h2>
                <button class="modal-close" onclick="hideModal('applicationsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="applicationsList" class="applications-list">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Event Volunteers Modal -->
    <div class="modal" id="eventVolunteersModal" aria-hidden="true">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 class="modal-title">Event Volunteers</h2>
                <button class="modal-close" onclick="hideModal('eventVolunteersModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="eventVolunteersList" class="volunteers-list">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

	<!-- Event Modal -->
	<div class="modal" id="eventModal">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title" id="eventModalTitle">Create Event</h2>
				<button class="modal-close" onclick="closeEventModal()">
					<i class="fas fa-times"></i>
				</button>
			</div>
			<div class="modal-body">
				<form method="POST" id="eventForm">
					<input type="hidden" name="action" id="eventFormAction" value="create_event">
					<input type="hidden" name="event_id" id="eventId">

					<div class="form-group">
						<label for="title">Event Title <span style="color: red;">*</span></label>
						<input type="text" id="title" name="title" class="form-control" required>
					</div>

					<div class="form-group">
						<label for="description">Description <span style="color: red;">*</span></label>
						<textarea id="description" name="description" class="form-control" required></textarea>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="event_start_date">Start Date <span style="color: red;">*</span></label>
								<input type="date" id="event_start_date" name="event_start_date" class="form-control" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="event_end_date">End Date <span style="color: red;">*</span></label>
								<input type="date" id="event_end_date" name="event_end_date" class="form-control" required>
							</div>
						</div>
					</div>

					<div class="form-group">
						<label for="event_time">Time</label>
						<input type="time" id="event_time" name="event_time" class="form-control">
					</div>

					<div class="form-group">
						<label for="location">Location <span style="color: red;">*</span></label>
						<input type="text" id="location" name="location" class="form-control" required>
					</div>

					<div class="form-group">
						<label for="max_volunteers">Maximum Volunteers <span style="color: red;">*</span></label>
						<input type="number" id="max_volunteers" name="max_volunteers" class="form-control" min="1" required>
					</div>

					<div class="form-group" id="statusGroup" style="display: none;">
						<label for="status">Status</label>
						<select id="status" name="status" class="form-control">
							<option value="upcoming">Upcoming</option>
							<option value="ongoing">Ongoing</option>
							<option value="completed">Completed</option>
							<option value="cancelled">Cancelled</option>
						</select>
					</div>

					<div class="form-actions">
						<button type="button" class="btn btn-secondary" onclick="closeEventModal()">Cancel</button>
						<button type="submit" class="btn btn-primary">
							<i class="fas fa-save"></i>
							<span id="eventSubmitText">Create Event</span>
						</button>
					</div>
				</form>
			</div>
		</div>
		</div>

	<script>
		// Toggle sidebar
		function toggleSidebar() {
			const sidebar = document.getElementById('sidebar');
			const overlay = document.getElementById('sidebarOverlay');
			sidebar.classList.toggle('active');
			overlay.classList.toggle('active');
		}

		// Handle logout
		function handleLogout() {
			Swal.fire({
                title: 'Logout Confirmation',
                text: "Are you sure you want to logout?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('logoutForm').submit();
                }
            });
		}

		function approveVolunteer(volunteerId) {
	    // hide the applications modal first so Swal appears on top
	    try { hideModal('applicationsModal'); } catch(e) {}
	    // allow the modal to hide before showing Swal
	    setTimeout(() => {
	        Swal.fire({
	            title: 'Confirm Approval',
	            text: 'Are you sure you want to approve this volunteer?',
	            icon: 'question',
	            showCancelButton: true,
	            confirmButtonColor: '#27ae60',
	            cancelButtonColor: '#95a5a6',
	            confirmButtonText: 'Yes, approve',
	            cancelButtonText: 'Cancel'
	        }).then(result => {
	            if (!result.isConfirmed) return;

	            const fd = new FormData();
	            fd.append('action', 'approve');
	            fd.append('volunteer_id', volunteerId);

	            fetch(window.location.pathname, {   // post to this same page (it returns JSON)
	                method: 'POST',
	                body: fd,
	                credentials: 'same-origin',
	                headers: { 'X-Requested-With': 'XMLHttpRequest' }
	            })
	            .then(r => r.text().then(t => ({ ok: r.ok, text: t })))
	            .then(({ ok, text }) => {
	                let data;
	                try { data = JSON.parse(text); } catch (e) {
	                    console.error('Invalid JSON response:', text);
	                    throw new Error('Server returned an unexpected response. Check console/network.');
	                }
	                if (data && data.success) {
	                    Swal.fire({ icon: 'success', title: 'Approved', text: data.message, timer: 1200, showConfirmButton: false })
	                        .then(() => location.reload());
	                } else {
	                    throw new Error(data.message || 'Failed to approve');
	                }
	            })
	            .catch(err => {
	                console.error('Approve error:', err);
	                Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Failed to approve' });
	            });
	        });
	    }, 0);
	}

	function openRejectModal(volunteerId, volunteerName) {
	    // close the applications modal so it "exits" immediately
	    try { hideModal('applicationsModal'); } catch (e) { /* ignore */ }

	    // small defer to allow modal to hide before showing Swal
	    setTimeout(() => {
	        Swal.fire({
	            title: `Reject application for: ${volunteerName}`,
	            input: 'textarea',
	            inputLabel: 'Reason for rejection',
	            inputPlaceholder: 'Provide a reason for rejection...',
	            inputAttributes: { 'aria-label': 'Rejection reason' },
	            showCancelButton: true,
	            confirmButtonText: 'Reject',
	            cancelButtonText: 'Cancel',
	            preConfirm: (value) => {
	                if (!value || !value.trim()) {
	                    Swal.showValidationMessage('Rejection reason is required');
	                    return false;
	                }
	                return value.trim();
	            }
	        }).then((result) => {
	            if (result.isConfirmed && result.value) {
	                const fd = new FormData();
	                fd.append('action', 'reject');
	                fd.append('volunteer_id', volunteerId);
	                fd.append('rejection_reason', result.value);

	                fetch(window.location.pathname, {
	                    method: 'POST',
	                    body: fd,
	                    credentials: 'same-origin',
	                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
	                })
	                .then(resp => resp.text().then(t => ({ ok: resp.ok, text: t })))
	                .then(({ ok, text }) => {
	                    let data;
	                    try { data = JSON.parse(text); } catch (e) {
	                        console.error('Invalid JSON response:', text);
	                        throw new Error('Server returned an unexpected response. Check console/network.');
	                    }
	                    if (data && data.success) {
	                        Swal.fire({
	                            icon: 'success',
	                            title: 'Rejected',
	                            text: data.message,
	                            timer: 1400,
	                            showConfirmButton: false
	                        }).then(() => location.reload());
	                    } else {
	                        throw new Error(data.message || 'Failed to reject');
	                    }
	                })
	                .catch(err => {
	                    console.error('Reject error:', err);
	                    Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Failed to reject' })
	                        .then(() => {
	                            // reopen the applications modal so user can continue from list
	                            try { showModal('applicationsModal'); } catch(e){}
	                        });
	                });
	            } else {
	                // user cancelled SweetAlert -> reopen applications modal (exit modal behaviour)
	                try { showModal('applicationsModal'); } catch(e){}
	            }
	        });
	    },  0);
	}

	// Helper functions for modals
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
}

// Updated showVolunteerApplications function
function showVolunteerApplications(eventId) {
    const list = document.getElementById('applicationsList');
    showModal('applicationsModal');
    list.innerHTML = '<div class="loading" style="text-align:center; padding:2rem;"><i class="fas fa-spinner fa-spin"></i> Loading requests...</div>';

    fetch(`community_service.php?event_id=${eventId}&ajax=1`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(html => {
        list.innerHTML = html;
        // ... NEW: normalize any server-rendered "Mark Hours" buttons to "Attendance"
        normalizeMarkHoursButtons(list);
    })
    .catch(error => {
        console.error('Error:', error);
        list.innerHTML = `
            <div class="empty-state" style="text-align:center; padding:2rem;">
                <i class="fas fa-exclamation-circle" style="font-size:3rem; color:#dc3545; margin-bottom:1rem;"></i>
                <h3>Error Loading Requests</h3>
                <p>Failed to load volunteer requests. Please try again.</p>
            </div>`;
    });
}

		function openEventModal(mode, eventData = null) {
    const modal = document.getElementById('eventModal');
    const title = document.getElementById('eventModalTitle');
    const form = document.getElementById('eventForm');
    const action = document.getElementById('eventFormAction');
    const submitText = document.getElementById('eventSubmitText');
    const statusGroup = document.getElementById('statusGroup');

    form.reset();

    // Set minimum date for event_start_date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('event_start_date').min = today;
    document.getElementById('event_end_date').min = today;

    // Add validation to prevent past dates
    document.getElementById('event_start_date').addEventListener('change', function() {
        const selectedDate = this.value;
        if (selectedDate < today) {
            alert('Start date cannot be in the past');

            this.value = today;
        }
        // Set end date min to start date
        document.getElementById('event_end_date').min = selectedDate;
    });

    if (mode === 'edit' && eventData) {
        title.textContent = 'Edit Event';
        action.value = 'update_event';
        submitText.textContent = 'Update Event';
        statusGroup.style.display = 'block';

        // Populate form with event data
        document.getElementById('eventId').value = eventData.id;
        document.getElementById('title').value = eventData.title;
        document.getElementById('description').value = eventData.description;
        document.getElementById('event_start_date').value = eventData.event_start_date;
        document.getElementById('event_end_date').value = eventData.event_end_date;
        document.getElementById('event_time').value = eventData.event_time;
        document.getElementById('location').value = eventData.location;
        document.getElementById('max_volunteers').value = eventData.max_volunteers;
        document.getElementById('status').value = eventData.status;
    } else {
        title.textContent = 'Create Event';
        action.value = 'create_event';
        submitText.textContent = 'Create Event';
        statusGroup.style.display = 'none';
    }

    modal.classList.add('active');
}

function closeEventModal() {
    const modal = document.getElementById('eventModal');
    modal.classList.remove('active');
}

function deleteEvent(eventId) {
    if (confirm('Are you sure you want to delete this event?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_event">
            <input type="hidden" name="event_id" value="${eventId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}



function showEventVolunteers(eventId) {
        const list = document.getElementById('eventVolunteersList');
        showModal('eventVolunteersModal');
        list.innerHTML = '<div class="loading" style="text-align:center; padding:2rem;"><i class="fas fa-spinner fa-spin"></i> Loading volunteers...</div>';

        fetch(`get_event_volunteers.php?event_id=${eventId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())

        .then(html => {
            list.innerHTML = html;
            // ... NEW: normalize any server-rendered "Mark Hours" buttons to "Attendance"
            normalizeMarkHoursButtons(list);
        })
        .catch(error => {
            console.error('Error:', error);
            list.innerHTML = `
                <div class="empty-state" style="text-align:center; padding:2rem;">
                    <i class="fas fa-exclamation-circle" style="font-size:3rem; color:#dc3545; margin-bottom:1rem;"></i>
                    <h3>Error Loading Volunteers</h3>
                    <p>Failed to load event volunteers. Please try again.</p>
                </div>`;
        });
    }

    // Add this event listener after other scripts
    document.getElementById('eventForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeEventModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                   
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Reload the page to show new event
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Something went wrong',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Something went wrong. Please try again.',
                timer: 2000,
                showConfirmButton: false
            });
        });
    });

    // Helper to open attendance modal: fetch proof image and populate modal
    function openAttendanceModal(volunteerId, volunteerName) {
        const modal = document.getElementById('attendanceModal');
        const nameEl = document.getElementById('attendanceVolunteerName');
        const idEl = document.getElementById('attendanceVolunteerId');
        const proofImg = document.getElementById('proofImage');
        const proofContainer = document.getElementById('proofContainer');
        const noProof = document.getElementById('noProofNotice');

        nameEl.textContent = volunteerName || 'Volunteer';
        idEl.value = volunteerId;
        // reset proof visibility
        proofImg.src = '';
        proofContainer.style.display = 'none';
        noProof.style.display = 'none';

        // fetch proof via AJAX
        fetch(`community_service.php?volunteer_proof=${encodeURIComponent(volunteerId)}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success && data.proof_path) {
                // construct absolute path if needed
                let src = data.proof_path;
                // If returned path is relative (uploads/...), ensure correct base
                if (src && !src.match(/^https?:\/\//i)) {
                    // attempt to build relative to webroot - adjust if your uploads root differs
                    src = src.startsWith('/') ? src : ('../../' + src);
                }
                proofImg.src = src;
                proofContainer.style.display = 'block';
            } else {
                noProof.style.display = 'block';
            }
            // show modal
            modal.classList.add('active');
        })
        .catch(err => {
            console.error('Error fetching proof:', err);
            noProof.style.display = 'block';
            modal.classList.add('active');
        });
    }

    function closeAttendanceModal() {
        const modal = document.getElementById('attendanceModal');
        if (modal) {
            modal.classList.remove('active');
        }
        // if we previously hid the event volunteers modal to show attendance, restore it
        if (window._reopenEventVolunteersAfterAttendance) {
            try {
                // small delay so the attendance modal fully hides before showing the underlying modal
                setTimeout(function() {
                    showModal('eventVolunteersModal');
                }, 80);
            } catch (e) {
                console.error('Failed to reopen event volunteers modal', e);
            } finally {
                // reset flag
                window._reopenEventVolunteersAfterAttendance = false;
            }
        }
    }

    // global shortcut helper for buttons in volunteers list (so existing markup can call this)
    function markAttendance(volunteerId, volunteerName) {
        // minimize / hide the event volunteers modal first so the attendance modal appears on top
        try {
            // remember we hid the event volunteers modal so we can reopen it when attendance modal closes
            window._reopenEventVolunteersAfterAttendance = true;
            hideModal('eventVolunteersModal');
        } catch (e) {
            // ignore if modal not present
            window._reopenEventVolunteersAfterAttendance = false;
        }

        // small timeout ensures hiding animation completes (if any) before opening attendance modal
        setTimeout(function() {
            openAttendanceModal(volunteerId, volunteerName);
        }, 50);
    }

    // submit attendance form via AJAX
    document.getElementById('attendanceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const fd = new FormData(form);

        fetch(window.location.pathname, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text().then(t => ({ ok: r.ok, text: t })))
        .then(({ ok, text }) => {
            let data;
            try { data = JSON.parse(text); } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Server returned an unexpected response. Check console/network.');
            }
            if (data && data.success) {
                // Prevent reopening the event volunteers modal — this is a confirmed attendance
                window._reopenEventVolunteersAfterAttendance = false;
                closeAttendanceModal();
                Swal.fire({ icon: 'success', title: 'Attendance', text: data.message, timer: 1400, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                throw new Error(data.message || 'Failed to confirm attendance');
            }
        })
        .catch(err => {
            console.error('Attendance error:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Failed to confirm attendance' });
        });
    });

// add helper function near other JS helpers
function normalizeMarkHoursButtons(container) {
    try {
        if (!container) container = document;
        const buttons = container.querySelectorAll('button, a'); // handle <a> as well if used
        buttons.forEach(el => {
            // normalize textual occurrences of "Mark Hours" -> "Attendance"
            // preserve icons and other HTML by replacing text nodes only
            let changed = false;
            el.childNodes.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE && node.nodeValue && node.nodeValue.trim().includes('Mark Hours')) {
                    node.nodeValue = node.nodeValue.replace(/Mark Hours/g, 'Attendance');
                    changed = true;
                }
            });
            // also check title / aria-label attributes
            ['title','aria-label'].forEach(attr => {
                const v = el.getAttribute(attr);
                if (v && v.includes('Mark Hours')) {
                    el.setAttribute(attr, v.replace(/Mark Hours/g, 'Attendance'));
                    changed = true;
                }
            });
            // if no text node matched but innerText contains the phrase (e.g. wrapped in spans), do a safe innerHTML replace
            if (!changed && el.innerText && el.innerText.includes('Mark Hours')) {
                // attempt to replace only the visible text portion while preserving HTML structure where possible
                el.innerHTML = el.innerHTML.replace(/Mark Hours/g, 'Attendance');
            }
        });
    } catch (e) {
        console.error('normalizeMarkHoursButtons error', e);
    }
}
	</script>
</body>
</html>