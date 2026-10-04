<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin or staff
if (!isset($_SESSION["loggedin"]) || ($_SESSION["user_type"] !== 'superadmin' && $_SESSION["user_type"] !== 'staff')) {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch overall hostel occupancy data
$overall_occupancy_query = "
    SELECT
        COUNT(DISTINCT b.id) AS total_beds,
        COUNT(DISTINCT hb.id) AS occupied_beds
    FROM
        beds b
    LEFT JOIN
        hostel_bookings hb ON b.id = hb.bed_id AND hb.status = 'booked'";
$overall_occupancy = $conn->query($overall_occupancy_query)->fetch_assoc();
$total_beds = $overall_occupancy['total_beds'];
$occupied_beds = $overall_occupancy['occupied_beds'];
$available_beds_count = $total_beds - $occupied_beds;

// Fetch hostel-wise occupancy
$hostel_occupancy_query = "
    SELECT
        h.name AS hostel_name,
        COUNT(DISTINCT b.id) AS total_beds_in_hostel,
        COUNT(DISTINCT hb.id) AS occupied_beds_in_hostel
    FROM
        hostels h
    LEFT JOIN
        rooms r ON h.id = r.hostel_id
    LEFT JOIN
        beds b ON r.id = b.room_id
    LEFT JOIN
        hostel_bookings hb ON b.id = hb.bed_id AND hb.status = 'booked'
    GROUP BY
        h.id
    ORDER BY
        h.name";
$hostel_occupancy = $conn->query($hostel_occupancy_query)->fetch_all(MYSQLI_ASSOC);

// Prepare data for the chart
$chart_labels = [];
$chart_data = [];
foreach ($hostel_occupancy as $hostel) {
    $chart_labels[] = $hostel['hostel_name'];
    $occupancy_rate = $hostel['total_beds_in_hostel'] > 0 ? round(($hostel['occupied_beds_in_hostel'] / $hostel['total_beds_in_hostel']) * 100, 2) : 0;
    $chart_data[] = $occupancy_rate;
}

// Fetch full list of students with their hostel details
$students_list_query = "
    SELECT
        s.full_name AS student_name,
        s.student_id_number,
        s.email,
        s.phone,
        h.name AS hostel_name,
        r.room_number,
        b.bed_number,
        hb.status AS booking_status
    FROM
        students s
    LEFT JOIN
        hostel_bookings hb ON s.id = hb.student_id
    LEFT JOIN
        beds b ON hb.bed_id = b.id
    LEFT JOIN
        rooms r ON b.room_id = r.id
    LEFT JOIN
        hostels h ON r.hostel_id = h.id
    ORDER BY
        s.full_name";
$students_list = $conn->query($students_list_query)->fetch_all(MYSQLI_ASSOC);

$conn->close();

include 'includes/header.php';
?>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<?php
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
    <div class="container-fluid">
        <h5 class="card-title fw-semibold mb-4">Hostel Report</h5>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Overall Occupancy</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-white">Total Beds</h6>
                                <h4 class="card-title"><?php echo $total_beds; ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-white">Available Beds</h6>
                                <h4 class="card-title"><?php echo $available_beds_count; ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-white">Occupied Beds</h6>
                                <h4 class="card-title"><?php echo $occupied_beds; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="card-title mt-4 mb-3">Hostel-wise Occupancy</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Hostel Name</th>
                                <th>Total Beds</th>
                                <th>Occupied Beds</th>
                                <th>Available Beds</th>
                                <th>Occupancy Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hostel_occupancy as $hostel):
                                $hostel_available = $hostel['total_beds_in_hostel'] - $hostel['occupied_beds_in_hostel'];
                                $occupancy_rate = $hostel['total_beds_in_hostel'] > 0 ? round(($hostel['occupied_beds_in_hostel'] / $hostel['total_beds_in_hostel']) * 100, 2) : 0;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($hostel['hostel_name']); ?></td>
                                    <td><?php echo htmlspecialchars($hostel['total_beds_in_hostel']); ?></td>
                                    <td><?php echo htmlspecialchars($hostel['occupied_beds_in_hostel']); ?></td>
                                    <td><?php echo htmlspecialchars($hostel_available); ?></td>
                                    <td><?php echo $occupancy_rate; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h5 class="card-title mt-5 mb-3">Occupancy Rate Visualization</h5>
                <div id="occupancyChart"></div>

                <h5 class="card-title mt-4 mb-3">Student Hostel Assignments</h5>
                <div class="table-responsive">
                    <table id="studentHostelTable" class="table table-striped" style="width:100%">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Hostel</th>
                                <th>Room</th>
                                <th>Bed</th>
                                <th>Booking Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_list as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['student_id_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['hostel_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['room_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['bed_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['booking_status'] ?? 'Not Assigned'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script src="../assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const chartData = <?php echo json_encode($chart_data, JSON_NUMERIC_CHECK); ?>;
    const chartLabels = <?php echo json_encode($chart_labels); ?>;

    var options = {
        series: [{
            name: 'Occupancy Rate',
            data: chartData
        }],
        chart: {
            type: 'bar',
            height: 350,
            toolbar: {
                show: true
            }
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                return val.toFixed(1) + "%";
            },
            offsetX: 25,
            style: {
                fontSize: '12px',
                colors: ["#304758"]
            }
        },
        xaxis: {
            categories: chartLabels,
            max: 100,
            min: 0,
            title: {
                text: "Occupancy Rate (%)"
            }
        },
        colors: ['#5D87FF']
    };

    var chart = new ApexCharts(document.querySelector("#occupancyChart"), options);
    chart.render();
});
</script>
<?php include 'includes/footer.php'; ?>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
// Wait for the document to be ready
$(document).ready(function() {
    // Initialize DataTables on the student list
    $('#studentHostelTable').DataTable();
});
</script>