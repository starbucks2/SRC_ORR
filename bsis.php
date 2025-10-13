<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>BSIS - Bachelor of Science in Information Systems</title>
  <link rel="icon" type="image/png" href="srclogo.png">
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- AOS -->
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <style>
    body { font-family: 'Poppins', sans-serif; }
    .hero-overlay { background: linear-gradient(135deg, rgba(15, 23, 42, 0.75) 0%, rgba(30, 64, 175, 0.85) 100%); }
    /* Plain CSS equivalent of Tailwind chip utility classes */
    .chip {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem; /* py-1 px-3 */
      border-radius: 9999px;    /* rounded-full */
      font-size: 0.75rem;       /* text-xs */
      font-weight: 600;         /* font-medium */
      background-color: #eff6ff;/* bg-blue-50 */
      color: #1d4ed8;           /* text-blue-700 */
      border: 1px solid #bfdbfe;/* border-blue-200 */
    }
    .chip i { margin-right: 0.5rem; }
  </style>
</head>
<body class="bg-gray-50">
  <?php include 'navbar.php'; ?>

  <!-- Hero -->
  <section class="relative min-h-[60vh] flex items-center overflow-hidden">
    <div class="absolute inset-0 bg-center bg-cover" style="background-image:url('SRC-Pics.png');"></div>
    <div class="absolute inset-0 hero-overlay"></div>
    <div class="relative container mx-auto px-4 py-16 text-white">
      <div class="max-w-3xl" data-aos="fade-up">
        <p class="uppercase tracking-wider text-blue-200 text-sm mb-2">Undergraduate Program</p>
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold leading-tight mb-4">Bachelor of Science in Information Systems (BSIS)</h1>
        <p class="text-base sm:text-lg text-blue-100 max-w-2xl">A 4-year degree that blends business, management, and information technology to design and manage information systems that drive organizational success.</p>
        <div class="mt-6 flex flex-wrap gap-2">
          <span class="chip"><i class="fa-solid fa-diagram-project mr-2"></i>Systems</span>
          <span class="chip"><i class="fa-solid fa-database mr-2"></i>Data</span>
          <span class="chip"><i class="fa-solid fa-briefcase mr-2"></i>Business</span>
          <span class="chip"><i class="fa-solid fa-user-tie mr-2"></i>Management</span>
        </div>
      </div>
    </div>
  </section>

  <!-- Overview -->
  <section class="py-12 sm:py-16 md:py-20 bg-white">
    <div class="container mx-auto px-4 grid lg:grid-cols-3 gap-8">
      <div class="lg:col-span-2" data-aos="fade-up">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 sm:p-8">
          <h2 class="text-2xl font-bold text-gray-900 mb-4">Program Overview</h2>
          <p class="text-gray-700 leading-relaxed">The BSIS program prepares students to analyze, design, develop, and manage information systems that support business operations and strategic decision-making. It combines technical knowledge with an understanding of business processes, making graduates versatile in both IT and management roles.</p>
          <div class="mt-6 grid sm:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-blue-50 border border-blue-100">
              <p class="font-semibold text-blue-900"><i class="fa-solid fa-clock mr-2"></i>Duration</p>
              <p class="text-blue-800 text-sm">4 Years (Undergraduate)</p>
            </div>
            <div class="p-4 rounded-xl bg-green-50 border border-green-100">
              <p class="font-semibold text-green-900"><i class="fa-solid fa-graduation-cap mr-2"></i>Degree</p>
              <p class="text-green-800 text-sm">Bachelor of Science in Information Systems</p>
            </div>
          </div>
        </div>
      </div>
      <aside data-aos="fade-left">
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-2xl shadow-xl p-6">
          <h3 class="text-xl font-semibold mb-2">Why Choose BSIS?</h3>
          <p class="text-blue-100 text-sm">Blend of tech and business. High industry demand. Strong career progression.</p>
          <a href="#apply" class="mt-4 inline-flex items-center px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 transition">
            <i class="fa-solid fa-paper-plane mr-2"></i> Inquire Now
          </a>
        </div>
      </aside>
    </div>
  </section>

  <!-- Skills -->
  <section class="py-12 bg-gray-50">
    <div class="container mx-auto px-4" data-aos="fade-up">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Skills You Will Learn</h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-sitemap text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Systems Analysis and Design</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-database text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Database Management</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-code text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Software Development Basics</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-diagram-project text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">IT Project Management</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-gear text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Business Process Management</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-network-wired text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Networking & Security Basics</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-chart-line text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Data Analytics & Decision Support</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Sample Curriculum -->
  <section class="py-12 bg-white">
    <div class="container mx-auto px-4" data-aos="fade-up">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Sample Courses</h2>
      <div class="grid md:grid-cols-2 gap-6">
        <div class="rounded-2xl border border-gray-100 shadow-sm p-6">
          <h3 class="font-semibold text-gray-800 mb-3"><i class="fa-solid fa-layer-group text-blue-600 mr-2"></i>Core IS/IT</h3>
          <ul class="list-disc pl-5 space-y-1 text-gray-700 text-sm">
            <li>Fundamentals of Programming</li>
            <li>Systems Analysis and Design</li>
            <li>Database Management Systems</li>
            <li>Enterprise Systems</li>
            <li>Information Security and Risk Management</li>
          </ul>
        </div>
        <div class="rounded-2xl border border-gray-100 shadow-sm p-6">
          <h3 class="font-semibold text-gray-800 mb-3"><i class="fa-solid fa-briefcase text-blue-600 mr-2"></i>Business & Management</h3>
          <ul class="list-disc pl-5 space-y-1 text-gray-700 text-sm">
            <li>Project Management</li>
            <li>Business Process Management</li>
            <li>E-Commerce and Digital Business</li>
            <li>Data Analytics and Decision Support</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- Careers -->
  <section class="py-12 bg-gray-50">
    <div class="container mx-auto px-4" data-aos="fade-up">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Career Opportunities</h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-user-gear text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Systems Analyst</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-magnifying-glass-chart text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Business Analyst</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-diagram-project text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">IT Project Manager</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-database text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Database Administrator</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-handshake-angle text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">IT Consultant</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-users-gear text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Information Systems Manager</p>
        </div>
        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
          <i class="fa-solid fa-headset text-blue-600 text-xl"></i>
          <p class="mt-3 font-semibold">Application Support Specialist</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section id="apply" class="py-16 bg-white">
    <div class="container mx-auto px-4" data-aos="fade-up">
      <div class="rounded-2xl p-8 border border-blue-100 bg-gradient-to-r from-blue-50 to-indigo-50 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
          <h3 class="text-2xl font-bold text-blue-900">Ready to explore BSIS?</h3>
          <p class="text-blue-800 text-sm">Join a future where business insight meets technology innovation.</p>
        </div>
        <div class="flex gap-3">
          <a href="about.php" class="px-5 py-3 rounded-lg bg-white text-blue-900 font-semibold border border-blue-200 hover:bg-blue-50 transition">Learn About SRC</a>
          <a href="#" class="px-5 py-3 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">Apply Now</a>
        </div>
      </div>
    </div>
  </section>

  <?php include 'footer.php'; ?>

  <!-- AOS JS -->
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      AOS.init({ duration: 800, easing: 'ease-out-quart', once: true, offset: 80 });
    });
  </script>
</body>
</html>
