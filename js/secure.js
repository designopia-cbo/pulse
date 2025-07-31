
document.addEventListener("contextmenu", event => event.preventDefault());


document.addEventListener("keydown", event => {
    if (
        event.key === "F12" ||
        (event.ctrlKey && event.shiftKey && event.key.toUpperCase() === "I") ||
        (event.ctrlKey && event.key.toUpperCase() === "U")
    ) {
        event.preventDefault();
    }
});


document.addEventListener("dragstart", event => event.preventDefault());


if (window.top !== window.self) {
    document.body.innerHTML = "<h1>Unauthorized Frame Access!</h1>";
}


let inactivityTimeout;
function resetInactivityTimer() {
    clearTimeout(inactivityTimeout);
    inactivityTimeout = setTimeout(() => {
        window.location.href = "/pulse/logout";
    }, 900000);
}
["mousemove", "keydown", "mousedown", "touchstart"].forEach(evt =>
    document.addEventListener(evt, resetInactivityTimer)
);
resetInactivityTimer();


(function() {
    let devtoolsCheck = setInterval(() => {
        let startTime = performance.now();
        debugger;
        if (performance.now() - startTime > 100) {
            window.location.href = "/pulse/logout";
            clearInterval(devtoolsCheck);
        }
    }, 1000);
})();


function preventSQLInjection(input) {
    const forbiddenPatterns = [/--/, /;/, /'/, /"/, /\b(SELECT|INSERT|DELETE|UPDATE|DROP|ALTER)\b/i];
    return forbiddenPatterns.some(pattern => pattern.test(input));
}
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("input, textarea").forEach(input => {
        input.addEventListener("input", event => {
            if (preventSQLInjection(event.target.value)) {
                event.target.value = ""; 
            }
        });
    });
});