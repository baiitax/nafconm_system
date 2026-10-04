<?php
require_once 'includes/header.php';
?>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Events</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li class="current">Events</li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Events Extended Section -->
    <section id="events-extended" class="events-extended section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row">
          <div class="col-lg-8">
            <!-- Events List -->
            <div class="events-list">
              <?php if (empty($events)): ?>
                <p class="text-center">There are no upcoming events at this time. Please check back later.</p>
              <?php else: ?>
                <?php foreach ($events as $event): ?>
                <div class="event-item" data-aos="fade-up">
                  <div class="event-date">
                    <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                    <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                  </div>
                  <div class="event-content">
                    <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                    <div class="event-meta">
                      <span><i class="bi bi-clock"></i> <?php echo date('h:i A', strtotime($event['event_date'])); ?></span>
                      <span><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($event['location']); ?></span>
                    </div>
                    <p class="event-description"><?php echo htmlspecialchars(substr($event['description'], 0, 150)) . '...'; ?></p>
                    <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn-event-details">Learn More <i class="bi bi-arrow-right"></i></a>
                  </div>
                </div><!-- End Event Item -->
                <?php endforeach; ?>
              <?php endif; ?>

              <!-- Pagination -->
              <div class="events-pagination" data-aos="fade-up" data-aos-delay="100">
                <ul class="pagination justify-content-center">
                  <li class="page-item disabled"><a class="page-link" href="#"><i class="bi bi-arrow-left"></i></a></li>
                  <li class="page-item active"><a class="page-link" href="#">1</a></li>
                  <li class="page-item"><a class="page-link" href="#">2</a></li>
                  <li class="page-item"><a class="page-link" href="#">3</a></li>
                  <li class="page-item"><a class="page-link" href="#"><i class="bi bi-arrow-right"></i></a></li>
                </ul>
              </div>
            </div><!-- End Events List -->
          </div>

          <div class="col-lg-4">
            <!-- Sidebar -->
            <div class="events-sidebar">
              <!-- Search Form -->
              <div class="sidebar-item search-form" data-aos="fade-up">
                <h4>Search Events</h4>
                <form action="">
                  <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search Events...">
                    <button class="btn" type="submit"><i class="bi bi-search"></i></button>
                  </div>
                </form>
              </div><!-- End Search Form -->

              <!-- Categories -->
              <div class="sidebar-item categories" data-aos="fade-up" data-aos-delay="100">
                <h4>Event Categories</h4>
                <ul class="list-unstyled">
                  <li><a href="#">Academic <span>(12)</span></a></li>
                  <li><a href="#">Sports <span>(7)</span></a></li>
                  <li><a href="#">Arts &amp; Culture <span>(9)</span></a></li>
                  <li><a href="#">Workshops <span>(5)</span></a></li>
                  <li><a href="#">Seminars <span>(8)</span></a></li>
                  <li><a href="#">Competitions <span>(6)</span></a></li>
                </ul>
              </div><!-- End Categories -->

              <!-- Upcoming Events -->
              <div class="sidebar-item upcoming-events" data-aos="fade-up" data-aos-delay="200">
                <h4>Upcoming Featured Events</h4>
                <div class="featured-event">
                  <img src="assets/img/education/events-5.webp" alt="Event" class="img-fluid">
                  <div class="featured-event-details">
                    <h5>Summer Leadership Camp</h5>
                    <span class="event-date"><i class="bi bi-calendar"></i> July 10-15, 2023</span>
                    <a href="#" class="btn-sm btn-register">Register Now</a>
                  </div>
                </div>
              </div><!-- End Upcoming Events -->

              <!-- Event Calendar -->
              <div class="sidebar-item event-calendar" data-aos="fade-up" data-aos-delay="300">
                <h4>Event Calendar</h4>
                <div class="calendar-widget">
                  <div class="calendar-header">
                    <h5>May 2023</h5>
                    <div class="calendar-nav">
                      <a href="#" class="prev-month"><i class="bi bi-chevron-left"></i></a>
                      <a href="#" class="next-month"><i class="bi bi-chevron-right"></i></a>
                    </div>
                  </div>
                  <table class="calendar-table">
                    <thead>
                      <tr>
                        <th>S</th>
                        <th>M</th>
                        <th>T</th>
                        <th>W</th>
                        <th>T</th>
                        <th>F</th>
                        <th>S</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td></td>
                        <td>1</td>
                        <td>2</td>
                        <td>3</td>
                        <td>4</td>
                        <td>5</td>
                        <td>6</td>
                      </tr>
                      <tr>
                        <td>7</td>
                        <td>8</td>
                        <td>9</td>
                        <td>10</td>
                        <td>11</td>
                        <td>12</td>
                        <td>13</td>
                      </tr>
                      <tr>
                        <td>14</td>
                        <td class="has-event">15</td>
                        <td>16</td>
                        <td>17</td>
                        <td>18</td>
                        <td>19</td>
                        <td>20</td>
                      </tr>
                      <tr>
                        <td>21</td>
                        <td class="has-event">22</td>
                        <td>23</td>
                        <td>24</td>
                        <td>25</td>
                        <td>26</td>
                        <td>27</td>
                      </tr>
                      <tr>
                        <td>28</td>
                        <td>29</td>
                        <td>30</td>
                        <td>31</td>
                        <td></td>
                        <td></td>
                        <td></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div><!-- End Event Calendar -->
            </div><!-- End Sidebar -->
          </div>
        </div>

      </div>

    </section><!-- /Events Extended Section -->

  </main>

<?php
  include 'includes/footer.php';
?>