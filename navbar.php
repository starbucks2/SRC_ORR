<!-- Navbar -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <img src="srclogo.png" alt="Becuran National High School Logo" class="h-12 w-12 mr-3">
                    <a href="#" class="text-xl font-bold text-blue-900">Santa Rita College of Pampanga</a>
                </div>
                
                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-8">
                    <a href="index.php" class="nav-link relative overflow-hidden text-gray-700 hover:text-blue-600 font-medium transition-all active:scale-95">Home</a>
                    <a href="about.php" class="nav-link relative overflow-hidden text-gray-700 hover:text-blue-600 font-medium transition-all active:scale-95">About</a>
                    
                    <a href="login.php" class="relative overflow-hidden bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-all duration-200 font-medium active:scale-95 shadow hover:shadow-md">Login</a>
                </div>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-gray-700 hover:text-blue-600 focus:outline-none">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="index.php" class="relative overflow-hidden block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-gray-50 transition-all active:scale-95">Home</a>
                <a href="about.php" class="relative overflow-hidden block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-gray-50 transition-all active:scale-95">About</a>
                
                <a href="login.php" class="relative overflow-hidden block px-3 py-2 rounded-md text-base font-medium text-white bg-blue-600 hover:bg-blue-700 transition-all active:scale-95">Login</a>
            </div>
        </div>
    </nav>
    <style>
      /* Ripple animation for navbar links */
      .nb-ripple { position: absolute; border-radius: 9999px; transform: scale(0); opacity: 0.2; pointer-events: none; }
      .nb-ripple-dark { background: rgba(0,0,0,0.35); }
      .nb-ripple-light { background: rgba(255,255,255,0.5); }
      @keyframes nb-rp {
        to { transform: scale(2.5); opacity: 0; }
      }
      .nb-animate { animation: nb-rp 500ms ease-out forwards; }
    </style>
    <script>
    // Mobile menu toggle
    document.addEventListener('DOMContentLoaded', function() {
        const menuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        menuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileMenu.classList.toggle('hidden');
        });
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileMenu.classList.contains('hidden') && !mobileMenu.contains(e.target) && e.target !== menuButton && !menuButton.contains(e.target)) {
                mobileMenu.classList.add('hidden');
            }
        });

        // Ripple on click for navbar links and buttons
        const clickable = document.querySelectorAll('nav a, #mobile-menu a');
        clickable.forEach(el => {
            el.addEventListener('click', function(ev) {
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const span = document.createElement('span');
                span.className = 'nb-ripple nb-animate nb-ripple-dark';
                span.style.width = span.style.height = size + 'px';
                span.style.left = (ev.clientX - rect.left - size / 2) + 'px';
                span.style.top = (ev.clientY - rect.top - size / 2) + 'px';
                this.appendChild(span);
                setTimeout(() => span.remove(), 520);
            }, { passive: true });
        });
    });
    </script>
