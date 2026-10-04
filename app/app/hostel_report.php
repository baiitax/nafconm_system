<?php
/**
 * NAFCONM - Official Hostel Allocation Manifest
 * Features: Multi-dimensional Data Grouping, Smart Bed Sorting, Enterprise Print Layout
 */
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// Fetch Active Session for the Report Header
$sess_query = $conn->query("SELECT name FROM sessions WHERE is_active = 1 LIMIT 1");
$current_session = $sess_query->num_rows > 0 ? $sess_query->fetch_assoc()['name'] : '2025/2026 Session';

// --- 1. FETCH AND MAP ENTIRE HOSTEL INFRASTRUCTURE ---
// We use a subquery to get the latest booking for each bed to avoid duplicate histories
$sql = "
    SELECT 
        h.name as hostel_name, h.gender,
        r.room_number, r.capacity,
        b.id as bed_id, b.bed_number, b.is_available,
        s.full_name, s.student_id_number, s.current_level, s.photo_path
    FROM hostels h
    JOIN rooms r ON h.id = r.hostel_id
    JOIN beds b ON r.id = b.room_id
    LEFT JOIN (
        SELECT bed_id, student_id FROM hostel_bookings
        WHERE id IN (SELECT MAX(id) FROM hostel_bookings GROUP BY bed_id)
    ) latest_booking ON b.id = latest_booking.bed_id
    LEFT JOIN students s ON latest_booking.student_id = s.id
    ORDER BY 
        h.name ASC, 
        r.room_number ASC, 
        b.id ASC -- Fallback sort to ensure Bed 1, Bed 2 order is maintained
";

$result = $conn->query($sql);

// --- 2. ORGANIZE DATA INTO A MULTI-DIMENSIONAL ARRAY ---
// Structure: $manifest['Hostel Name']['Room Number'][] = Bed Data
$manifest = [];
$total_beds = 0;
$occupied_beds = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $hostel = $row['hostel_name'] . ' (' . $row['gender'] . ' Wing)';
        $room = $row['room_number'];
        
        if (!isset($manifest[$hostel])) {
            $manifest[$hostel] = [];
        }
        if (!isset($manifest[$hostel][$room])) {
            $manifest[$hostel][$room] = [];
        }
        
        $manifest[$hostel][$room][] = $row;
        
        $total_beds++;
        if ($row['is_available'] == 0) {
            $occupied_beds++;
        }
    }
}

$date_printed = date('d M, Y \a\t h:i A');
$page_title = "Hostel Manifest";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    
    /* Screen UI Styles */
    .manifest-card { border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 30px; background: #fff; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
    .manifest-header { background: #1e293b; color: #fff; padding: 15px 20px; font-weight: 900; font-size: 18px; text-transform: uppercase; letter-spacing: 1px; }
    
    .room-header { background: #f8fafc; border-bottom: 2px solid #cbd5e1; border-top: 1px solid #e2e8f0; padding: 10px 20px; font-weight: bold; color: #0f172a; display: flex; justify-content: space-between; }
    
    .bed-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .bed-table th, .bed-table td { padding: 12px 20px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    .bed-table th { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: bold; background: #fff; }
    .bed-table tbody tr:last-child td { border-bottom: none; }
    .bed-table tbody tr:hover { background-color: #f8fafc; }
    
    .bed-label { font-weight: 900; font-family: monospace; font-size: 14px; }
    .status-badge { font-size: 11px; padding: 4px 10px; border-radius: 50px; font-weight: bold; text-transform: uppercase; }
    .status-occupied { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .status-vacant { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    
    /* Print Button */
    .no-print-btn { 
        display: inline-flex; align-items: center; justify-content: center; gap: 10px; 
        padding: 12px 30px; background: #1e293b; color: white; text-decoration: none; 
        border-radius: 50px; cursor: pointer; border: none; font-weight: 900; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.1); transition: 0.3s;
    }
    .no-print-btn:hover { background: #0f172a; transform: translateY(-2px); }

    /* Print Specific Styles - The "Scale" */
    @media print {
        @page { size: A4 portrait; margin: 10mm 15mm; }
        body { background: #fff; padding: 0; color: #000; font-family: 'Times New Roman', Times, serif; }
        
        /* Hide UI elements */
        .no-print-btn, #sidebar, #topbar, .navbar, footer, .page-wrapper > *:not(.container-fluid) { display: none !important; }
        .page-wrapper, .container-fluid { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        
        /* Document Print Headers */
        .print-only-header { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .print-only-header img { width: 70px; margin-bottom: 5px; }
        .print-only-header h1 { margin: 0; font-size: 22px; text-transform: uppercase; color: #000; }
        .print-only-header p { margin: 3px 0 0 0; font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .doc-title { text-align: center; font-size: 16px; font-weight: bold; text-decoration: underline; margin-bottom: 20px; text-transform: uppercase; }

        /* Force page breaks between hostels */
        .manifest-card { border: none; box-shadow: none; margin-bottom: 0; page-break-after: always; }
        
        .manifest-header { background: transparent !important; color: #000 !important; padding: 0; font-size: 18px; border-bottom: 2px solid #000; margin-bottom: 10px; }
        .room-header { background: #f1f1f1 !important; border: 1px solid #000; padding: 5px 10px; color: #000 !important; }
        
        /* Print Table Scaling */
        .bed-table { border: 1px solid #000; margin-bottom: 15px; page-break-inside: avoid; }
        .bed-table th, .bed-table td { border: 1px solid #000; padding: 6px; font-size: 12px; color: #000 !important; }
        .bed-table th { background: #f1f1f1 !important; color: #000 !important; }
        
        /* Strip background colors for printers */
        .status-badge { border: none; background: transparent !important; padding: 0; }
        .status-occupied { color: #000 !important; font-weight: bold; }
        .status-vacant { color: #666 !important; font-style: italic; }
        
        /* Signature Block (Print Only) */
        .print-signature { display: flex !important; justify-content: space-between; margin-top: 40px; page-break-inside: avoid; }
        .sign-box { text-align: center; width: 30%; font-size: 12px; }
        .sign-line { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; }
    }
    
    /* Hide print elements on screen */
    .print-only-header, .print-signature { display: none; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Hostel Manifest & Roster</h3>
            <p class="text-muted small mb-0">Complete breakdown of bed allocations for the active session.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-3 align-items-center">
            <div class="text-end">
                <div class="small fw-bold text-muted text-uppercase">Occupancy Rate</div>
                <div class="fs-5 fw-black text-dark"><?= $total_beds > 0 ? round(($occupied_beds / $total_beds) * 100) : 0 ?>% Full</div>
            </div>
            <button class="no-print-btn" onclick="window.print()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print Official Manifest
            </button>
        </div>
    </div>

    <?php if (empty($manifest)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white mt-4">
            <i class="ti ti-building-off fs-1 text-muted opacity-25 mb-3 d-block"></i>
            <h4 class="fw-black text-dark">No Infrastructure Found</h4>
            <p class="text-muted mb-0">You need to create Hostels, Rooms, and Beds in the Infrastructure settings before a manifest can be generated.</p>
        </div>
    <?php else: ?>

        <!-- LOOP THROUGH HOSTEL BLOCKS -->
        <?php foreach ($manifest as $hostel_name => $rooms): ?>
            
            <div class="manifest-card">
                
                <!-- Print Only Official Header -->
                <div class="print-only-header">
                    <img src="../assets/images/logos/favicon.png" alt="Logo">
                    <h1>Nafisatu College of Nursing & Midwifery</h1>
                    <p>Office of the Hostel Warden</p>
                    <div class="doc-title">OFFICIAL HOSTEL MANIFEST — <?= htmlspecialchars($current_session) ?></div>
                </div>

                <!-- Hostel Block Title -->
                <div class="manifest-header d-flex justify-content-between align-items-center">
                    <div><i class="ti ti-building me-2"></i> <?= htmlspecialchars($hostel_name) ?></div>
                </div>

                <!-- LOOP THROUGH ROOMS IN THIS HOSTEL -->
                <?php foreach ($rooms as $room_number => $beds): ?>
                    <div class="room-header">
                        <span>Room <?= htmlspecialchars($room_number) ?></span>
                        <span class="small fw-medium opacity-75">Capacity: <?= count($beds) ?> Beds</span>
                    </div>
                    
                    <table class="bed-table">
                        <thead>
                            <tr>
                                <th width="15%">Bed Number</th>
                                <th width="15%">Status</th>
                                <th width="20%">Matriculation No.</th>
                                <th width="35%">Student Name</th>
                                <th width="15%">Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- LOOP THROUGH BEDS IN THIS ROOM -->
                            <?php foreach ($beds as $bed): ?>
                                <tr>
                                    <td class="bed-label text-dark"><?= htmlspecialchars($bed['bed_number']) ?></td>
                                    <td>
                                        <?php if ($bed['is_available'] == 0 && !empty($bed['student_id_number'])): ?>
                                            <span class="status-badge status-occupied">Occupied</span>
                                        <?php else: ?>
                                            <span class="status-badge status-vacant">Vacant</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-family: monospace; font-weight: bold; font-size: 13px;">
                                        <?= !empty($bed['student_id_number']) ? htmlspecialchars($bed['student_id_number']) : '<span class="text-muted opacity-50">-</span>' ?>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        <?= !empty($bed['full_name']) ? strtoupper(htmlspecialchars($bed['full_name'])) : '<span class="text-muted opacity-50">Empty Space</span>' ?>
                                    </td>
                                    <td>
                                        <?= !empty($bed['current_level']) ? htmlspecialchars($bed['current_level']) : '<span class="text-muted opacity-50">-</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
                
                <!-- Print Only Signatures (Renders at the bottom of each Hostel block's page) -->
                <div class="print-signature">
                    <div class="sign-box">
                        <div class="sign-line">Hostel Warden (Sign & Date)</div>
                    </div>
                    <div class="sign-box" style="font-size: 10px; color: #666;">
                        Generated via NAFCONM Portal<br>Printed: <?= $date_printed ?>
                    </div>
                    <div class="sign-box">
                        <div class="sign-line">Dean of Student Affairs</div>
                    </div>
                </div>

            </div>

        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>