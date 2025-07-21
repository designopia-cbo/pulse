<?php
require_once('init.php');

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Error message handling
$error_message = !empty($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['error_message']); // Clear error message
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS | Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/preline@latest/dist/preline.css">
    <link rel="stylesheet" href="https://preline.co/assets/css/main.min.css">
</head>
<body class="bg-gray-100 dark:bg-neutral-900 flex items-center justify-center h-screen">

<div class="mt-7 bg-white border border-gray-200 rounded-xl shadow-lg dark:bg-neutral-900 dark:border-neutral-700 p-6 sm:p-7 w-96">
    <div class="text-center">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
            <h1 class="text-4xl font-bold text-blue-600">
                MSSD PULSE
            </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-neutral-400">
            Personnel and Unified Labor Services Engine
        </p>

        <hr class="my-6 border-t border-gray-300 dark:border-neutral-700">

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <p class="text-red-600 text-sm mt-2"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
    </div>

    <div class="mt-5">
        <form action="login_process" method="POST" id="loginForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="grid gap-y-4">

                <!-- Username Field -->
                <div>
                    <label for="username" class="block text-sm mb-2 dark:text-white">Username</label>
                    <input type="text" id="username" name="username"
                        class="py-2.5 px-4 w-full border-gray-200 rounded-lg sm:text-sm focus:ring-blue-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
                        required />
                </div>

                <!-- Password Field with View Toggle -->
                <div>
                  <label for="password" class="block text-sm mb-2 dark:text-white">Password</label>
                  <div class="relative">
                    <input 
                      type="password" 
                      id="password" 
                      name="password"
                      class="py-2.5 px-4 w-full border-gray-200 rounded-lg sm:text-sm focus:ring-blue-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
                      required
                    />
                    <button 
                      type="button"
                      aria-label="Toggle password visibility"
                      class="absolute inset-y-0 end-0 flex items-center z-20 px-3 cursor-pointer text-gray-400 rounded-e-md focus:outline-hidden focus:text-blue-600 dark:text-neutral-600 dark:focus:text-blue-500"
                      onclick="togglePasswordVisibility('password', this)"
                      tabindex="-1"
                    >
                      <!-- Eye Icon (visible by default) -->
                      <svg class="shrink-0 size-3.5 eye-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                      <!-- Eye Off Icon (hidden by default) -->
                      <svg class="shrink-0 size-3.5 eye-off-icon hidden" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.43 10.43 0 0 1 12 19c-7 0-10-7-10-7a17.7 17.7 0 0 1-1.67-2.68"></path>
                        <path d="m1 1 22 22"></path>
                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12"></path>
                      </svg>
                    </button>
                  </div>
                </div>
                <!-- End Password Field -->

                <div class="py-1"></div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-3 px-4 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">Sign in</button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const eye = btn.querySelector('.eye-icon');
    const eyeOff = btn.querySelector('.eye-off-icon');

    if (input.type === 'password') {
        input.type = 'text';
        eye.classList.add('hidden');
        eyeOff.classList.remove('hidden');
    } else {
        input.type = 'password';
        eye.classList.remove('hidden');
        eyeOff.classList.add('hidden');
    }
}
</script>

<script src="/pulse/js/secure.js"></script>
              
</body>
</html>