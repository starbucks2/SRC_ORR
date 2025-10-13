<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>COE - Bachelor of Secondary Education Major in English</title>
  <link rel="icon" type="image/png" href="srclogo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <style>
    body { font-family: 'Poppins', sans-serif; }
    .hero-overlay { background: linear-gradient(135deg, rgba(15, 23, 42, 0.75) 0%, rgba(30, 64, 175, 0.85) 100%); }
    .chip { display:inline-flex; align-items:center; padding:0.25rem 0.75rem; border-radius:9999px; font-size:0.75rem; font-weight:600; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
    .chip i { margin-right:0.5rem; }
  </style>
</head>
<body class="bg-gray-50">
  <?php include 'navbar.php'; ?>

  <section class="relative min-h-[60vh] flex items-center overflow-hidden">
    <div class="absolute inset-0 bg-center bg-cover" style="background-image:url('SRC-Pics.png');"></div>
    <div class="absolute inset-0 hero-overlay"></div>
    <div class="relative container mx-auto px-4 py-16 text-white">
      <div class="max-w-3xl" data-aos="fade-up">
        <p class="uppercase tracking-wider text-blue-200 text-sm mb-2">Undergraduate Program</p>
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold leading-tight mb-4">Bachelor of Secondary Education (BSEd) Major in English</h1>
        <p class="text-base sm:text-lg text-blue-100 max-w-2xl">Equips future secondary educators with advanced competencies in English language, literature, and pedagogy for junior and senior high school teaching.</p>
        <div class="mt-6 flex flex-wrap gap-2">
          <span class="chip"><i class="fa-solid fa-language"></i>Language</span>
          <span class="chip"><i class="fa-solid fa-book"></i>Literature</span>
          <span class="chip"><i class="fa-solid fa-chalkboard-user"></i>Pedagogy</span>
        </div>
      </div>
    </div>
  </section>

  <section class="py-12 sm:py-16 md:py-20 bg-white">
    <div class="container mx-auto px-4 grid lg:grid-cols-3 gap-8">
      <div class="lg:col-span-2" data-aos="fade-up">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 sm:p-8">
          <h2 class="text-2xl font-bold text-gray-900 mb-4">Program Overview</h2>
          <p class="text-gray-700 leading-relaxed">The BSEd English program focuses on developing proficient, reflective, and innovative English teachers ready to handle diverse classrooms and integrate technology for effective learning.</p>
          <div class="mt-6 grid sm:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-blue-50 border border-blue-100">
              <p class="font-semibold text-blue-900"><i class="fa-solid fa-clock mr-2"></i>Duration</p>
              <p class="text-blue-800 text-sm">4 Years (Undergraduate)</p>
            </div>
            <div class="p-4 rounded-xl bg-green-50 border border-green-100">
              <p class="font-semibold text-green-900"><i class="fa-solid fa-graduation-cap mr-2"></i>Degree</p>
              <p class="text-green-800 text-sm">Bachelor of Secondary Education Major in English</p>
            </div>
          </div>
        </div>
      </div>
      <aside data-aos="fade-left">
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-2xl shadow-xl p-6">
          <h3 class="text-xl font-semibold mb-2">Why Choose BSEd English?</h3>
          <p class="text-blue-100 text-sm">Strong language foundation, practical classroom strategies, and assessment literacy.</p>
          <a href="#apply" class="mt-4 inline-flex items-center px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 transition">
            <i class="fa-solid fa-paper-plane mr-2"></i> Inquire Now
          </a>
        </div>
      </aside>
    </div>
  </section>

  <section id="apply" class="py-16 bg-white">
    <div class="container mx-auto px-4" data-aos="fade-up">
      <div class="rounded-2xl p-8 border border-blue-100 bg-gradient-to-r from-blue-50 to-indigo-50 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
          <h3 class="text-2xl font-bold text-blue-900">Ready to explore BSEd English?</h3>
          <p class="text-blue-800 text-sm">Inspire learners through effective communication and critical thinking.</p>
        </div>
        <div class="flex gap-3">
          <a href="about.php" class="px-5 py-3 rounded-lg bg-white text-blue-900 font-semibold border border-blue-200 hover:bg-blue-50 transition">Learn About SRC</a>
          <a href="#" class="px-5 py-3 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">Apply Now</a>
        </div>
      </div>
    </div>
  </section>

  <?php include 'footer.php'; ?>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){ AOS.init({ duration: 800, easing: 'ease-out-quart', once: true, offset: 80 }); });
  </script>
</body>
</html>
