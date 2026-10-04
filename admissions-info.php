<?php
  $pageTitle = "Admissions Information";
  include 'includes/header.php';
?>
<main class="main">
    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Admissions</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li class="current">Admissions</li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Admissions Section -->
    <section id="admissions-info" class="admissions-info section">
        <div class="container" data-aos="fade-up" data-aos-delay="100">
            <div class="row gy-4">
                <div class="col-lg-8">
                    <!-- Admission Process -->
                    <div class="card mb-4">
                        <div class="card-header"><h3 class="mb-0">Our Admission Process</h3></div>
                        <div class="card-body">
                            <p>Follow these simple steps to begin your journey at NAFCONM. We have a transparent and straightforward admission process to ensure all qualified candidates have an equal opportunity.</p>
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Step 1: Check Requirements</h4>
                                        <p>Ensure you meet the O'Level credit requirements and other criteria for your desired program before starting your application.</p>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Step 2: Complete Online Application</h4>
                                        <p>Fill out the online application form accurately on our <a href="apply.php">application page</a>. You will need to provide personal, academic, and guardian information.</p>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Step 3: Upload Documents</h4>
                                        <p>Scan and upload all required documents, including your birth certificate, O'Level result slip, and a passport photograph.</p>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Step 4: Entrance Examination & Interview</h4>
                                        <p>Shortlisted candidates will be invited for an entrance examination and an oral interview. Dates will be communicated via email.</p>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Step 5: Check Admission Status</h4>
                                        <p>Check your admission status on the portal using the token provided during application. Successful candidates will receive an admission letter.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tuition & Fees -->
                    <div class="card">
                        <div class="card-header"><h3 class="mb-0">Tuition & Fees</h3></div>
                        <div class="card-body">
                            <p>NAFCONM is committed to providing quality education at an affordable cost. Our fee structure is transparent, with options for flexible payment plans.</p>
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Program</th>
                                        <th>Tuition Fee (Per Session)</th>
                                        <th>Other Fees</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>General Nursing</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    </tr>
                                    <tr>
                                        <td>Basic Midwifery</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    </tr>
                                    <tr>
                                        <td>Community Health (CHEW)</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    </tr>
                                    <tr>
                                        <td>Post-Basic Nursing</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    </tr>
                                </tbody>
                            </table>
                            <small class="form-text text-muted">*Fees are subject to review. Other fees include medicals, library, and practical fees. Accommodation is charged separately.</small>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Admission Requirements -->
                    <div class="card mb-4">
                        <div class="card-header"><h3 class="mb-0">Admission Criteria</h3></div>
                        <div class="card-body">
                            <p>To be eligible for admission, all candidates must meet the following criteria:</p>
                            <ul class="list-unstyled">
                                <li class="d-flex mb-2"><i class="bi bi-check-circle-fill text-success me-2 mt-1"></i> <span>Minimum of 5 O'Level credits in WAEC/NECO/NABTEB.</span></li>
                                <li class="d-flex mb-2"><i class="bi bi-check-circle-fill text-success me-2 mt-1"></i> <span>Credits must include English Language, Mathematics, Physics, Chemistry, and Biology.</span></li>
                                <li class="d-flex mb-2"><i class="bi bi-check-circle-fill text-success me-2 mt-1"></i> <span>Results must be obtained in not more than two (2) sittings.</span></li>
                                <li class="d-flex mb-2"><i class="bi bi-check-circle-fill text-success me-2 mt-1"></i> <span>Must be between the ages of 17 and 25.</span></li>
                                <li class="d-flex"><i class="bi bi-check-circle-fill text-success me-2 mt-1"></i> <span>Successful performance in the entrance examination and interview.</span></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Apply Now Card -->
                    <div class="card text-center bg-primary text-white">
                        <div class="card-body">
                            <h3 class="card-title text-white">Ready to Apply?</h3>
                            <p class="card-text">Begin your journey to becoming a world-class healthcare professional today.</p>
                            <a href="apply.php" class="btn btn-light btn-lg">Start Your Application</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php
  include 'includes/footer.php';
?>