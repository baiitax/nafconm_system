<?php
require_once 'includes/header.php';

$event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$event_id) {
    header('Location: events.php');
    exit();
}

$pageTitle = htmlspecialchars($event['title']);
?>
  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0"><?php echo htmlspecialchars($event['title']); ?></h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li><a href="events.php">Events</a></li>
            <li class="current"><?php echo htmlspecialchars($event['title']); ?></li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Event Section -->
    <section id="event" class="event section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row">
          <div class="col-lg-8">
            <div class="event-image mb-4" data-aos="fade-up">
              <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="img-fluid rounded">
            </div>

            <div class="event-meta mb-4" data-aos="fade-up" data-aos-delay="100">
              <div class="row g-3">
                <div class="col-md-4">
                  <div class="meta-item">
                    <i class="bi bi-calendar-date"></i>
                    <span><?php echo date('F d, Y', strtotime($event['event_date'])); ?></span>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="meta-item">
                    <i class="bi bi-clock"></i>
                    <span><?php echo date('h:i A', strtotime($event['event_date'])); ?></span>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="meta-item">
                    <i class="bi bi-geo-alt"></i>
                    <span><?php echo htmlspecialchars($event['location']); ?></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="event-content" data-aos="fade-up" data-aos-delay="200">
              <h2>Annual Science Exhibition</h2>
              <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>

              <!-- Note: Highlights and Schedule would need to be stored in the DB, perhaps as JSON, to be dynamic -->
              <h3 class="mt-4">Event Highlights</h3>
              <ul class="event-highlights">
                <li>
                  <i class="bi bi-check-circle"></i>
                  <span>Interactive student presentations of scientific experiments</span>
                </li>
                <li>
                  <i class="bi bi-check-circle"></i>
                  <span>Special lecture by renowned physicist Dr. Robert Jenkins</span>
                </li>
                <li>
                  <i class="bi bi-check-circle"></i>
                  <span>Robotics competition with prizes for top three teams</span>
                </li>
                <li>
                  <i class="bi bi-check-circle"></i>
                  <span>Science demonstrations by faculty members</span>
                </li>
                <li>
                  <i class="bi bi-check-circle"></i>
                  <span>Exhibition of innovative student projects</span>
                </li>
              </ul>

              <h3 class="mt-4">Event Schedule</h3>
              <div class="schedule-table">
                <div class="schedule-row">
                  <div class="schedule-time">3:00 PM - 3:30 PM</div>
                  <div class="schedule-activity">
                    <h4>Opening Ceremony</h4>
                    <p>Welcome address by Principal and introduction to the event</p>
                  </div>
                </div>
                <div class="schedule-row">
                  <div class="schedule-time">3:30 PM - 4:30 PM</div>
                  <div class="schedule-activity">
                    <h4>Student Project Presentations</h4>
                    <p>Selected students showcase their scientific innovations</p>
                  </div>
                </div>
                <div class="schedule-row">
                  <div class="schedule-time">4:30 PM - 5:15 PM</div>
                  <div class="schedule-activity">
                    <h4>Guest Lecture</h4>
                    <p>Special lecture on "Future of Quantum Computing" by Dr. Robert Jenkins</p>
                  </div>
                </div>
                <div class="schedule-row">
                  <div class="schedule-time">5:15 PM - 5:45 PM</div>
                  <div class="schedule-activity">
                    <h4>Robotics Demonstration</h4>
                    <p>Live demonstration of student-built robots and automation projects</p>
                  </div>
                </div>
                <div class="schedule-row">
                  <div class="schedule-time">5:45 PM - 6:00 PM</div>
                  <div class="schedule-activity">
                    <h4>Award Ceremony &amp; Closing</h4>
                    <p>Distribution of certificates and recognition of outstanding projects</p>
                  </div>
                </div>
              </div>

              <div class="event-gallery mt-5" data-aos="fade-up" data-aos-delay="300">
                <h3>Event Gallery</h3>
                <p>Images from previous year's science exhibition:</p>
                <div class="row g-4 mt-2">
                  <div class="col-md-4">
                    <a href="assets/img/education/events-1.webp" class="glightbox">
                      <img src="assets/img/education/events-1.webp" alt="Event Gallery" class="img-fluid rounded">
                    </a>
                  </div>
                  <div class="col-md-4">
                    <a href="assets/img/education/events-2.webp" class="glightbox">
                      <img src="assets/img/education/events-2.webp" alt="Event Gallery" class="img-fluid rounded">
                    </a>
                  </div>
                  <div class="col-md-4">
                    <a href="assets/img/education/events-3.webp" class="glightbox">
                      <img src="assets/img/education/events-3.webp" alt="Event Gallery" class="img-fluid rounded">
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="event-sidebar">
              <div class="sidebar-widget registration-form" data-aos="fade-left" data-aos-delay="200">
                <h3>Register for this Event</h3>
                <form>
                  <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" required="">
                  </div>
                  <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" required="">
                  </div>
                  <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone">
                  </div>
                  <div class="mb-3">
                    <label for="graduation_year" class="form-label">Graduation Year</label>
                    <input type="number" class="form-control" id="graduation_year" placeholder="e.g., 2018">
                  </div>
                  <div class="d-grid">
                    <button type="submit" class="btn btn-register">Register Now</button>
                  </div>
                </form>
              </div>

              <div class="sidebar-widget organizer-info" data-aos="fade-left" data-aos-delay="300">
                <h3>Event Organizer</h3>
                <div class="organizer-details">
                  <div class="organizer-image">
                    <img src="assets/img/person/person-f-8.webp" class="img-fluid rounded" alt="Organizer">
                  </div>
                  <div class="organizer-content">
                    <h4>Mrs. Zainab Ibrahim</h4>
                    <p class="organizer-position">Alumni Relations Officer</p>
                    <p>For queries related to the event, please contact:</p>
                    <div class="organizer-contact">
                      <p><i class="bi bi-envelope"></i> alumni@nafconm.edu.ng</p>
                      <p><i class="bi bi-telephone"></i> +234 801 234 5679</p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="sidebar-widget related-events" data-aos="fade-left" data-aos-delay="400">
                <h3>Related Events</h3>
                <div class="related-event-item">
                  <div class="related-event-date">
                    <span class="day">15</span>
                    <span class="month">Nov</span>
                  </div>
                  <div class="related-event-info">
                    <h4>Mathematics Olympiad</h4>
                    <p><i class="bi bi-geo-alt"></i> Room 203, East Wing</p>
                  </div>
                </div>
                <div class="related-event-item">
                  <div class="related-event-date">
                    <span class="day">05</span>
                    <span class="month">Dec</span>
                  </div>
                  <div class="related-event-info">
                    <h4>Literature Festival</h4>
                    <p><i class="bi bi-geo-alt"></i> Central Library</p>
                  </div>
                </div>
                <div class="related-event-item">
                  <div class="related-event-date">
                    <span class="day">18</span>
                    <span class="month">Dec</span>
                  </div>
                  <div class="related-event-info">
                    <h4>Annual Sports Meet</h4>
                    <p><i class="bi bi-geo-alt"></i> School Ground</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </section><!-- /Event Section -->

  </main>

<?php
  include 'includes/footer.php';
?>