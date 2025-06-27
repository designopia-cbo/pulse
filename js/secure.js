// Disable right-click
document.addEventListener("contextmenu", event => event.preventDefault());

// Block F12, Ctrl+Shift+I, Ctrl+U (View Source)
document.addEventListener("keydown", event => {
    if (event.key === "F12" || 
        (event.ctrlKey && event.shiftKey && event.key === "I") || 
        (event.ctrlKey && event.key === "U") || 
        (event.ctrlKey && event.key === "S") || 
        (event.ctrlKey && event.key === "A")) {
        event.preventDefault();
    }
});

// Prevent text selection & copying
//document.addEventListener("selectstart", event => event.preventDefault());
//document.addEventListener("copy", event => event.preventDefault());
//document.addEventListener("cut", event => event.preventDefault());
//document.addEventListener("paste", event => event.preventDefault());

// Block drag-and-drop functionality
document.addEventListener("dragstart", event => event.preventDefault());

// DevTools detection with forced logout
(function() {
    let devtoolsCheck = setInterval(() => {
        let startTime = performance.now();
        debugger;
        if (performance.now() - startTime > 100) {
            // Force logout: break session by redirecting to logout.php
            window.location.href = "/pulse/logout";
            clearInterval(devtoolsCheck);
        }
    }, 1000);
})();

// Prevent Clickjacking (Ensure page can't be embedded in an iframe)
if (window.top !== window.self) {
    document.body.innerHTML = "<h1>Unauthorized Frame Access!</h1>";
}

// Detect Console Access Trick (Monitor if console is open secretly)
(function() {
    console.log("%cHidden Console Test", "color: transparent");
    Object.defineProperty(console, '_commandLineAPI', {
        get: function() {
        }
    });
})();

// Monitor Suspicious Key Combinations (Prevent security bypass via keys)
document.addEventListener("keydown", event => {
    if ((event.ctrlKey && event.key === "J") || // Ctrl+J (Console)
        (event.ctrlKey && event.key === "K") || // Ctrl+K (DevTools)
        (event.ctrlKey && event.key === "E")) { // Ctrl+E (Debugger)
        event.preventDefault();
    }
});

// Auto Logout for Inactive Users (5 mins of inactivity)
let timeout;
function resetTimer() {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        window.location.href = "/pulse/logout"; // Redirect to logout
    }, 300000); // 5 minutes (300000 ms)
}

document.addEventListener("mousemove", resetTimer);
document.addEventListener("keydown", resetTimer);
resetTimer(); // Initialize inactivity tracker

// Basic SQL Injection Prevention (Frontend validation)
function preventSQLInjection(input) {
    const forbiddenPatterns = [/--/, /;/, /'/, /"/, /\b(SELECT|INSERT|DELETE|UPDATE|DROP|ALTER)\b/i];
    return forbiddenPatterns.some(pattern => pattern.test(input));
}

// Apply validation to all input fields
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("input, textarea").forEach(input => {
        input.addEventListener("input", event => {
            if (preventSQLInjection(event.target.value)) {
                event.target.value = ""; // Clear the input
            }
        });
    });
});