<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Admission Application - N-Portal</title>
    <link rel="stylesheet" href="assets/css/form-style.css">
</head>

<?php include './includes/header.php'; ?>
    
    <div class="form-container">
        <h1>Student Admission Application</h1>
        <p>Please fill out the form below to apply for admission. Fields marked with * are required.</p>

        <?php if (isset($_SESSION['status_message'])): ?>
            <div class="status-message <?php echo strpos(strtolower($_SESSION['status_message']), 'error') !== false ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($_SESSION['status_message']); ?>
            </div>
            <?php unset($_SESSION['status_message']); ?>
        <?php endif; ?>

        <form action="process_application.php" method="post" enctype="multipart/form-data">
            
            <h2>Personal Details</h2>
            <div class="form-group">
                <label for="first_name">First Name *</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" id="middle_name" name="middle_name">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name *</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number *</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of Birth *</label>
                <input type="date" id="date_of_birth" name="date_of_birth" required>
            </div>
            <div class="form-group">
                <label for="address">Full Address *</label>
                <textarea id="address" name="address" required></textarea>
            </div>

            <h2>Academic Information</h2>
            <div class="form-group">
                <label for="previous_school">Previous School Attended</label>
                <input type="text" id="previous_school" name="previous_school">
            </div>
            <div class="form-group">
                <label for="program_of_interest">Program of Interest *</label>
                <select id="program_of_interest" name="program_of_interest" required>
                    <option value="">-- Select a Program --</option>
                    <option value="Nursing">Nursing</option>
                    <option value="Midwifery">Midwifery</option>
                    <option value="Health Sciences">Health Sciences</option>
                    <!-- Add other programs as needed -->
                </select>
            </div>

            <h2>Required Documents</h2>
            <p>Please upload the following documents. Allowed formats: PDF, JPG, PNG. Max size: 2MB each.</p>
            
            <div class="form-group">
                <label for="birth_certificate">Birth Certificate *</label>
                <input type="file" id="birth_certificate" name="documents[birth_certificate]" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            
            <div class="form-group">
                <label for="previous_transcript">Previous School Transcript/Results *</label>
                <input type="file" id="previous_transcript" name="documents[previous_transcript]" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>

            <div class="form-group">
                <label for="photo">Passport-sized Photo *</label>
                <input type="file" id="photo" name="documents[photo]" accept=".jpg,.jpeg,.png" required>
            </div>

            <div class="form-group">
                <button type="submit">Submit Application</button>
            </div>

        </form>
    </div>
</body>
</html>