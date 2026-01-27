<?php include 'db.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/head_meta.php'; ?>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- AOS (Animate On Scroll) CSS -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <!-- Custom styles -->
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .hero-gradient {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.8) 0%, rgba(29, 78, 216, 0.9) 100%);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .nav-link {
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            color: #93c5fd !important;
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(to right, #2563eb, #1d4ed8);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .btn-primary:hover {
            background: linear-gradient(to right, #1d4ed8, #1e40af);
            transform: translateY(-2px);
        }
        .section-title {
            position: relative;
            display: inline-block;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #3b82f6;
        }
        .stat-card {
            background: linear-gradient(135deg,rgb(0, 0, 0) 0%,rgb(238, 233, 233) 100%);
            border-left: 4px solid #0ea5e9;
        }
        .stat-card:nth-child(2) {
            border-left-color:rgb(154, 238, 19);
            background: linear-gradient(135deg,rgb(8, 8, 8) 0%, #d1fae5 100%);
        }
        .stat-card:nth-child(3) {
            border-left-color: #f59e0b;
            background: linear-gradient(135deg,rgb(0, 0, 0) 0%, #fef3c7 100%);
        }
        .stat-card:nth-child(4) {
            border-left-color: #ef4444;
            background: linear-gradient(135deg,rgb(3, 3, 3) 0%, #fecaca 100%);
        }
        /* Click animation styles for hero CTAs */
        .ripple-effect { position: absolute; border-radius: 9999px; transform: scale(0); opacity: 0.7; pointer-events: none; }
        .ripple-light { background: rgba(255,255,255,0.35); }
        .ripple-dark { background: rgba(0,0,0,0.2); }
        @keyframes ripple {
            to { transform: scale(2.75); opacity: 0; }
        }
        .animate-ripple { animation: ripple 600ms ease-out forwards; }
    </style>
</head>
<body class="bg-gray-50">
     <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <section id="home" class="relative min-h-screen flex items-center justify-center overflow-hidden px-4 sm:px-0">
        <div class="absolute inset-0 bg-black opacity-50"></div>
        <div class="absolute inset-0" style="background-image: url('SRC-Pics.png'); background-size: cover; background-position: center; background-repeat: no-repeat; z-index: -1;"></div>
            <div class="container mx-auto px-2 sm:px-4 text-center text-white relative z-10">
            <h1 class="text-3xl sm:text-4xl md:text-6xl font-bold mb-4 sm:mb-6 animate-fade-in" data-aos="fade-up">Welcome to Santa Rita College of Pampanga</h1>
            <p class="text-base sm:text-xl md:text-2xl mb-6 sm:mb-8 max-w-3xl mx-auto animate-fade-in-delay" data-aos="fade-up" data-aos-delay="150">
                A premier educational institution empowering students through innovative learning and research excellence.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-3 sm:gap-4" data-aos="fade-up" data-aos-delay="250">
                <a href="#research" class="cta-animate relative overflow-hidden btn-primary text-white px-8 py-4 rounded-lg text-lg font-semibold transition-all duration-300 flex items-center justify-center shadow-lg hover:shadow-xl active:scale-95">
                    <i class="fas fa-book-open mr-3"></i> What is Research?               </a>
                <a href="about.php" class="cta-animate relative overflow-hidden bg-white text-blue-900 hover:bg-gray-100 px-8 py-4 rounded-lg text-lg font-semibold transition-all duration-300 flex items-center justify-center shadow-lg hover:shadow-xl active:scale-95">
                    <i class="fas fa-info-circle mr-3"></i> Learn About Us
                </a>
            </div>
        </div>
        
        <!-- Floating elements for visual interest -->
        <div class="absolute top-20 left-10 text-blue-200 opacity-30 animate-bounce">
            <i class="fas fa-graduation-cap text-6xl"></i>
        </div>
        <div class="absolute bottom-20 right-10 text-blue-200 opacity-30 animate-bounce" style="animation-delay: 1s;">
            <i class="fas fa-book text-6xl"></i>
        </div>
        <div class="absolute top-1/2 right-10 text-blue-200 opacity-30 animate-bounce" style="animation-delay: 2s;">
            <i class="fas fa-microscope text-6xl"></i>
        </div>
    </section>


    <!-- Mission, Vision, Core Values Section -->
    <section id="principles" class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-14" data-aos="fade-up">
                <h2 class="section-title text-2xl sm:text-3xl md:text-4xl font-bold text-gray-800 mb-4">Our Philosophy, Mission, and Vision</h2>
                <p class="text-gray-600">Guiding principles that center our community and inspire excellence.</p>
            </div>
            <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
                <!-- Philosophy Card -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 text-center card-hover" data-aos="fade-up" data-aos-delay="0">
                    <div class="w-14 h-14 mx-auto mb-5 flex items-center justify-center text-gray-800">
                        <i class="fas fa-brain text-4xl"></i>
                    </div>
                    <h3 class="flex items-baseline justify-center gap-1 mb-3">
                        <span class="text-4xl font-extrabold text-blue-900 leading-none">P</span>
                        <span class="text-xl font-semibold text-gray-800">hilosophy</span>
                    </h3>
                    <p class="text-gray-600 text-sm leading-relaxed max-w-xs mx-auto">We believe that education is transforming God-centered individuals in a nurturing learning environment.</p>
                </div>

                <!-- Mission Card -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 text-center card-hover" data-aos="fade-up" data-aos-delay="120">
                    <div class="w-14 h-14 mx-auto mb-5 flex items-center justify-center text-gray-800">
                        <i class="fas fa-rocket text-4xl"></i>
                    </div>
                    <h3 class="flex items-baseline justify-center gap-1 mb-3">
                        <span class="text-4xl font-extrabold text-blue-900 leading-none">M</span>
                        <span class="text-xl font-semibold text-gray-800">ission</span>
                    </h3>
                    <p class="text-gray-600 text-sm leading-relaxed max-w-xs mx-auto">A Center of Excellence dedicated to the transformation of individuals for the service of God and Humanity.</p>
                </div>

                <!-- Vision Card -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 text-center card-hover" data-aos="fade-up" data-aos-delay="240">
                    <div class="w-14 h-14 mx-auto mb-5 flex items-center justify-center text-gray-800">
                        <i class="fas fa-binoculars text-4xl"></i>
                    </div>
                    <h3 class="flex items-baseline justify-center gap-1 mb-3">
                        <span class="text-4xl font-extrabold text-blue-900 leading-none">V</span>
                        <span class="text-xl font-semibold text-gray-800">ision</span>
                    </h3>
                    <p class="text-gray-600 text-sm leading-relaxed max-w-xs mx-auto">We are dedicated to develop and nurture individuals.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Research Section -->
    <section id="research" class="py-12 sm:py-16 md:py-20 bg-gray-50">
    <div class="container mx-auto px-2 sm:px-4">
            <div class="text-center mb-10 sm:mb-16">
                <h2 class="section-title text-2xl sm:text-3xl md:text-4xl font-bold text-gray-800 mb-4 sm:mb-6 mx-auto" data-aos="fade-up">What is Research?</h2>
                <p class="text-base sm:text-lg text-gray-600 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="120">
                Research is the systematic process of investigating, analyzing, and interpreting information to answer questions or solve problems. It involves gathering data, examining sources, and forming conclusions based on evidence. Research can be conducted in various fields such as science, humanities, and social sciences. Its purpose is to expand knowledge, develop new theories, and inform decision-making. Effective research requires critical thinking, objectivity, and a clear methodology to produce reliable and meaningful results.
                </p>
            </div>
            
          
            </div>
        </div>
    </section>


  <?php include 'footer.php'; ?>

    <!-- AOS (Animate On Scroll) JS -->
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        AOS.init({
          duration: 800,
          easing: 'ease-out-quart',
          once: true,
          offset: 80
        });
      });
    </script>

    <!-- Scripts -->
    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const menuButton = document.getElementById('mobile-menu-button');
            const menu = document.getElementById('mobile-menu');
            if (!menuButton.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    window.scrollTo({
                        top: target.offsetTop - 80,
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu if open
                    document.getElementById('mobile-menu').classList.add('hidden');
                }
            });
        });

        // Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Students by Strand Chart
            new Chart(document.getElementById('studentsChart'), {
                type: 'pie',
                data: {
                    labels: <?= json_encode($strands) ?>,
                    datasets: [{
                        label: 'Students by Strand',
                        data: <?= json_encode($strandCounts) ?>,
                        backgroundColor: [
                            '#3B82F6', '#10B981', '#F59E0B', '#6366F1', '#EF4444'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Research Status Chart
            new Chart(document.getElementById('researchChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending'],
                    datasets: [{
                        label: 'Research Papers',
                        data: [<?= $researchStatus['Approved'] ?>, <?= $researchStatus['Pending'] ?>],
                        backgroundColor: ['#10B981', '#F59E0B'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Students by Grade Level Chart
            new Chart(document.getElementById('gradeChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_keys($gradeStats)) ?>,
                    datasets: [{
                        label: 'Number of Students',
                        data: <?= json_encode(array_values($gradeStats)) ?>,
                        backgroundColor: '#3B82F6',
                        borderColor: '#2563EB',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Students'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Grade Level'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Research by Year Chart
            new Chart(document.getElementById('yearChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_keys($researchByYear)) ?>,
                    datasets: [{
                        label: 'Research Submissions',
                        data: <?= json_encode(array_values($researchByYear)) ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        borderColor: '#3B82F6',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Submissions'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Year'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>

    <!-- Attach ripple on click for elements with .cta-animate -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.cta-animate').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const ripple = document.createElement('span');
                    ripple.className = 'ripple-effect animate-ripple ' + (this.classList.contains('btn-primary') ? 'ripple-light' : 'ripple-dark');
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                    this.appendChild(ripple);
                    // Cleanup
                    setTimeout(() => ripple.remove(), 650);
                }, { passive: true });
            });
        });
    </script>
</body>
</html>