<?php
/**
 * NAFCONM - Hostel Infrastructure Manager
 * Features: Auto-Bed Generation, MySQL Transactions, Capacity Tracking, CSRF Protection
 */
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 1. ADD NEW HOSTEL (BLOCK) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_hostel'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $name = htmlspecialchars(trim($_POST['hostel_name']));
        $gender = htmlspecialchars($_POST['hostel_gender']);

        $stmt = $conn->prepare("INSERT INTO hostels (name, gender) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $gender);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = "success|Hostel Block '$name' created successfully.";
        } else {
            $_SESSION['msg'] = "error|Failed to create Hostel. It may already exist.";
        }
        $stmt->close();
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 2. ADD ROOM & AUTO-GENERATE BEDS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_room'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $hostel_id = intval($_POST['hostel_id']);
        $room_number = htmlspecialchars(trim($_POST['room_number']));
        $capacity = intval($_POST['capacity']);

        if ($capacity < 1 || $capacity > 20) {
            $_SESSION['msg'] = "warning|Invalid capacity. Must be between 1 and 20 beds.";
        } else {
            $conn->begin_transaction();
            try {
                // A. Create the Room
                $stmt_r = $conn->prepare("INSERT INTO rooms (hostel_id, room_number, capacity) VALUES (?, ?, ?)");
                $stmt_r->bind_param("isi", $hostel_id, $room_number, $capacity);
                $stmt_r->execute();
                $new_room_id = $conn->insert_id;
                $stmt_r->close();

                // B. Auto-Generate the Beds
                $stmt_b = $conn->prepare("INSERT INTO beds (room_id, bed_number, is_available) VALUES (?, ?, 1)");
                for ($i = 1; $i <= $capacity; $i++) {
                    $bed_label = "Bed " . $i;
                    $stmt_b->bind_param("is", $new_room_id, $bed_label);
                    $stmt_b->execute();
                }
                $stmt_b->close();

                $conn->commit();
                $_SESSION['msg'] = "success|Room $room_number created with $capacity beds auto-generated.";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['msg'] = "error|Database Error: Could not create room and beds.";
            }
        }
    }
    header("Location: " . basename($_SERVER['PHP_SELF']) . "?tab=rooms");
    exit;
}

// --- 3. DELETE HOSTEL / ROOM ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_entity'])) {
    $id = intval($_POST['entity_id']);
    $type = $_POST['entity_type']; // 'hostel' or 'room'
    
    if ($type === 'hostel') {
        $stmt = $conn->prepare("DELETE FROM hostels WHERE id = ?");
    } else {
        $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    }
    
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['msg'] = "success|Successfully deleted.";
    } else {
        $_SESSION['msg'] = "error|Cannot delete. There may be students currently occupying this space.";
    }
    $stmt->close();
    header("Location: " . basename($_SERVER['PHP_SELF']) . ($type === 'room' ? "?tab=rooms" : ""));
    exit;
}

// --- 4. FETCH AGGREGATED DATA ---
// Overall Stats
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM hostels) as total_hostels,
        (SELECT COUNT(*) FROM rooms) as total_rooms,
        (SELECT COUNT(*) FROM beds) as total_beds,
        (SELECT COUNT(*) FROM beds WHERE is_available = 1) as available_beds
")->fetch_assoc();

// Hostels List
$hostels = $conn->query("
    SELECT h.id, h.name, h.gender, 
           COUNT(DISTINCT r.id) as room_count, 
           COUNT(b.id) as total_beds, 
           SUM(b.is_available) as available_beds
    FROM hostels h
    LEFT JOIN rooms r ON h.id = r.hostel_id
    LEFT JOIN beds b ON r.id = b.room_id
    GROUP BY h.id
    ORDER BY h.name ASC
");

// Rooms List
$rooms = $conn->query("
    SELECT r.id, r.room_number, r.capacity, h.name as hostel_name, h.gender,
           (SELECT COUNT(*) FROM beds WHERE room_id = r.id AND is_available = 1) as available_beds
    FROM rooms r
    JOIN hostels h ON r.hostel_id = h.id
    ORDER BY h.name ASC, r.room_number ASC
");

$active_tab = $_GET['tab'] ?? 'hostels';
$page_title = "Hostel Infrastructure";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-left: 4px solid transparent; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .stat-card.primary { border-left-color: var(--bs-primary); }
    .stat-card.warning { border-left-color: var(--bs-warning); }
    .stat-card.success { border-left-color: var(--bs-success); }
    .stat-card.info { border-left-color: var(--bs-info); }
    
    .nav-pills .nav-link { border-radius: 50px; font-weight: 700; color: #64748b; padding: 10px 24px; margin-right: 10px; border: 1px solid transparent; }
    .nav-pills .nav-link.active { background-color: #1e293b; color: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .nav-pills .nav-link:hover:not(.active) { background-color: #f8fafc; border-color: #e2e8f0; }
    
    .table-hover tbody tr { transition: background-color 0.2s; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }
</style>

<div class="container-fluid py-4">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Hostel Infrastructure</h3>
            <p class="text-muted small mb-0">Create blocks, define rooms, and auto-generate bed spaces.</p>
        </div>
    </div>

    <!-- KPI WIDGETS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card primary shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted text-uppercase fw-bold mb-1" style="font-size: 11px;">Total Hostels</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['total_hostels'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-primary-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-building text-primary fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card info shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-info text-uppercase fw-bold mb-1" style="font-size: 11px;">Total Rooms</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['total_rooms'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-info-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-door text-info fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card warning shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-warning text-uppercase fw-bold mb-1" style="font-size: 11px;">Total Beds</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['total_beds'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-warning-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-bed text-warning fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card success shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-success text-uppercase fw-bold mb-1" style="font-size: 11px;">Available Spaces</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['available_beds'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-success-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-check text-success fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABS NAV -->
    <ul class="nav nav-pills mb-4" id="hostelTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab === 'hostels' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#hostels" type="button" role="tab"><i class="ti ti-building me-2"></i>Hostel Blocks</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab === 'rooms' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#rooms" type="button" role="tab"><i class="ti ti-door-enter me-2"></i>Rooms & Beds</button>
        </li>
    </ul>

    <!-- TABS CONTENT -->
    <div class="tab-content" id="hostelTabsContent">
        
        <!-- TAB 1: HOSTEL BLOCKS -->
        <div class="tab-pane fade <?= $active_tab === 'hostels' ? 'show active' : '' ?>" id="hostels" role="tabpanel">
            <div class="row g-4">
                
                <!-- Create Hostel Form -->
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-plus text-primary me-2"></i>Create New Block</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <div class="form-floating mb-3">
                                    <input type="text" name="hostel_name" class="form-control" placeholder="e.g. Block A" required>
                                    <label>Hostel / Block Name</label>
                                </div>
                                
                                <div class="form-floating mb-4">
                                    <select name="hostel_gender" class="form-select fw-bold" required>
                                        <option value="Female">Female Wing</option>
                                        <option value="Male">Male Wing</option>
                                    </select>
                                    <label>Designated Gender</label>
                                </div>
                                
                                <button type="submit" name="add_hostel" class="btn btn-primary w-100 rounded-pill fw-bold py-3 shadow-sm">Save Hostel Block</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Hostel List Table -->
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark">Active Hostels</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-4 py-3 fw-bold">Hostel Name</th>
                                            <th class="py-3 fw-bold">Wing</th>
                                            <th class="py-3 fw-bold text-center">Rooms</th>
                                            <th class="py-3 fw-bold text-center">Capacity</th>
                                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($hostels && $hostels->num_rows > 0): 
                                            // Reset pointer for dropdown
                                            $hostels_dropdown = []; 
                                            while ($h = $hostels->fetch_assoc()): 
                                                $hostels_dropdown[] = $h;
                                        ?>
                                            <tr>
                                                <td class="ps-4 py-3 fw-bold text-dark"><i class="ti ti-building me-2 text-muted"></i><?= htmlspecialchars($h['name']) ?></td>
                                                <td><span class="badge <?= $h['gender'] == 'Female' ? 'bg-danger-subtle text-danger' : 'bg-info-subtle text-info' ?> border"><?= htmlspecialchars($h['gender']) ?></span></td>
                                                <td class="text-center fw-bold"><?= $h['room_count'] ?></td>
                                                <td class="text-center">
                                                    <div class="small"><span class="text-success fw-bold"><?= $h['available_beds'] ?: 0 ?></span> / <?= $h['total_beds'] ?: 0 ?> Available</div>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <form method="POST" class="delete-form d-inline">
                                                        <input type="hidden" name="entity_id" value="<?= $h['id'] ?>">
                                                        <input type="hidden" name="entity_type" value="hostel">
                                                        <button type="button" class="btn btn-light text-danger btn-sm rounded-circle p-2 border delete-btn" data-name="<?= htmlspecialchars($h['name']) ?>"><i class="ti ti-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; else: ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted">No hostels created yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- TAB 2: ROOMS & BEDS -->
        <div class="tab-pane fade <?= $active_tab === 'rooms' ? 'show active' : '' ?>" id="rooms" role="tabpanel">
            <div class="row g-4">
                
                <!-- Create Room Form -->
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-door-enter text-info me-2"></i>Add Room to Hostel</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="alert bg-info-subtle text-info border-0 small mb-4 rounded-3">
                                <i class="ti ti-info-circle me-1"></i> Beds are automatically generated based on the capacity you specify.
                            </div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <div class="form-floating mb-3">
                                    <select name="hostel_id" class="form-select fw-bold" required>
                                        <option value="">-- Select Target Hostel --</option>
                                        <?php foreach ($hostels_dropdown ?? [] as $hd): ?>
                                            <option value="<?= $hd['id'] ?>"><?= htmlspecialchars($hd['name']) ?> (<?= $hd['gender'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Hostel Block</label>
                                </div>
                                
                                <div class="row g-2 mb-4">
                                    <div class="col-8">
                                        <div class="form-floating">
                                            <input type="text" name="room_number" class="form-control fw-bold text-uppercase" placeholder="A-101" required>
                                            <label>Room Identifier (e.g. A-101)</label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-floating">
                                            <input type="number" name="capacity" class="form-control font-monospace fw-bold" placeholder="4" min="1" max="20" required>
                                            <label>Beds</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="add_room" class="btn btn-info w-100 text-white rounded-pill fw-bold py-3 shadow-sm" <?= empty($hostels_dropdown) ? 'disabled' : '' ?>>Generate Room & Beds</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Rooms List Table -->
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark">Generated Rooms Inventory</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light text-muted small text-uppercase position-sticky top-0 shadow-sm" style="z-index: 10;">
                                        <tr>
                                            <th class="ps-4 py-3 fw-bold">Room</th>
                                            <th class="py-3 fw-bold">Hostel Block</th>
                                            <th class="py-3 fw-bold text-center">Beds Created</th>
                                            <th class="py-3 fw-bold text-center">Available</th>
                                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($rooms && $rooms->num_rows > 0): while ($r = $rooms->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <span class="badge bg-dark text-white font-monospace px-2 py-1 fs-6"><?= htmlspecialchars($r['room_number']) ?></span>
                                                </td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($r['hostel_name']) ?></span> <small class="text-muted">(<?= $r['gender'] ?>)</small></td>
                                                <td class="text-center font-monospace fw-bold"><?= $r['capacity'] ?></td>
                                                <td class="text-center">
                                                    <?php if($r['available_beds'] == 0): ?>
                                                        <span class="badge bg-danger-subtle text-danger px-2 py-1">FULL</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success-subtle text-success px-2 py-1"><?= $r['available_beds'] ?> Free</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <form method="POST" class="delete-form d-inline">
                                                        <input type="hidden" name="entity_id" value="<?= $r['id'] ?>">
                                                        <input type="hidden" name="entity_type" value="room">
                                                        <button type="button" class="btn btn-light text-danger btn-sm rounded-circle p-2 border delete-btn" data-name="Room <?= htmlspecialchars($r['room_number']) ?>"><i class="ti ti-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; else: ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted">No rooms created yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- SweetAlert2 for Toast Notifications & Confirmations -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Delete Confirmations
    const deleteBtns = document.querySelectorAll('.delete-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('.delete-form');
            const itemName = this.getAttribute('data-name');
            
            Swal.fire({
                title: 'Delete ' + itemName + '?',
                html: `Are you sure? This will also delete all associated records (like beds) and cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#1e293b',
                confirmButtonText: 'Yes, Delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create hidden field to simulate the submit button name
                    const hiddenAction = document.createElement('input');
                    hiddenAction.type = 'hidden';
                    hiddenAction.name = 'delete_entity';
                    hiddenAction.value = '1';
                    form.appendChild(hiddenAction);
                    
                    form.submit();
                }
            });
        });
    });

    // 2. SweetAlert Notifications based on PHP Sessions
    <?php if (isset($_SESSION['msg'])): ?>
        <?php 
            list($type, $msg) = explode('|', $_SESSION['msg']); 
            unset($_SESSION['msg']); 
        ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: '<?= $type === "success" ? "success" : ($type === "error" ? "error" : "warning") ?>',
            title: '<?= addslashes($msg) ?>'
        });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>