<?php
// Start session only if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>

<nav class="bg-blue-900 text-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="index.php" class="text-2xl font-bold">BNHS Research Hub</a>
            </div>

            <!-- Hamburger Menu (Mobile) -->
            <div class="md:hidden">
                <button id="menu-btn" class="text-white focus:outline-none">
                    ☰
                </button>
            </div>

            <!-- Navigation Links -->
            <div id="menu" class="hidden md:flex md:space-x-6 absolute md:relative top-16 md:top-auto left-0 md:left-auto w-full md:w-auto bg-blue-900 md:bg-transparent md:flex-row flex-col text-center md:text-left">
                <a href="index.php" class="block py-2 px-4 md:inline hover:bg-blue-700 md:hover:bg-transparent">Home</a>
                <a href="repository.php" class="block py-2 px-4 md:inline hover:bg-blue-700 md:hover:bg-transparent">Repository</a>
                <a href="about.php" class="block py-2 px-4 md:inline hover:bg-blue-700 md:hover:bg-transparent">About</a>
                <a href="contact.php" class="block py-2 px-4 md:inline hover:bg-blue-700 md:hover:bg-transparent">Contact</a>
            </div>

            <!-- Authentication Links -->
            <div class="relative">
                <?php if (isset($_SESSION['email'])): ?>
                    <button id="user-menu-btn" class="flex items-center bg-green-500 px-4 py-2 rounded hover:bg-green-600 focus:outline-none">
                        <?= htmlspecialchars($_SESSION['email']); ?> ▼
                    </button>
                    <div id="user-menu" class="hidden absolute right-0 mt-2 bg-white text-black rounded shadow-md w-48">
                        <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-200">Dashboard</a>
                        <a href="logout.php" class="block px-4 py-2 hover:bg-gray-200">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="bg-blue-500 px-4 py-2 rounded hover:bg-blue-600">Login</a>
                    <a href="register.php" class="bg-yellow-500 px-4 py-2 rounded hover:bg-yellow-600">Register</a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>

<!-- JavaScript for Mobile Menu & User Dropdown -->
<script>
    document.getElementById("menu-btn").addEventListener("click", function() {
        document.getElementById("menu").classList.toggle("hidden");
    });

    document.getElementById("user-menu-btn")?.addEventListener("click", function() {
        document.getElementById("user-menu").classList.toggle("hidden");
    });
</script>

</body>
</html>
