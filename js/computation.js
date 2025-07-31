// Constants for time boundaries
const MORNING_START = '08:00';  // 8:00 AM
const MORNING_END = '12:00';    // 12:00 PM
const AFTERNOON_START = '13:00'; // 1:00 PM
const AFTERNOON_END = '17:00';   // 5:00 PM
const TOTAL_REQUIRED_MINUTES = 480; // 8 hours

/**
 * Converts time string to minutes since midnight
 * @param {string} timeStr - Time in HH:mm format (24-hour)
 * @returns {number} Minutes since midnight
 */
function timeToMinutes(timeStr) {
    if (!timeStr) return null;
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
}

/**
 * Calculates tardiness and undertime for morning session
 * @param {string} timeIn - Time in (HH:mm)
 * @param {string} timeOut - Time out (HH:mm)
 * @returns {number} Total minutes of tardiness and undertime for morning
 */
function calculateMorningTU(timeIn, timeOut) {
    let total = 0;
    const morningStartMinutes = timeToMinutes(MORNING_START);
    const morningEndMinutes = timeToMinutes(MORNING_END);
    
    // If no time in and out, consider absent for morning (4 hours = 240 minutes)
    if (!timeIn && !timeOut) return 240;
    
    // If only time in exists, consider half day absent
    if (timeIn && !timeOut) return 240;
    
    // If only time out exists, consider half day absent
    if (!timeIn && timeOut) return 240;
    
    const actualTimeIn = timeToMinutes(timeIn);
    const actualTimeOut = timeToMinutes(timeOut);
    
    // Calculate tardiness (late arrival)
    if (actualTimeIn > morningStartMinutes) {
        total += actualTimeIn - morningStartMinutes;
    }
    
    // Calculate undertime (early departure)
    if (actualTimeOut < morningEndMinutes) {
        total += morningEndMinutes - actualTimeOut;
    }
    
    return total;
}

/**
 * Calculates tardiness and undertime for afternoon session
 * @param {string} timeIn - Time in (HH:mm)
 * @param {string} timeOut - Time out (HH:mm)
 * @returns {number} Total minutes of tardiness and undertime for afternoon
 */
function calculateAfternoonTU(timeIn, timeOut) {
    let total = 0;
    const afternoonStartMinutes = timeToMinutes(AFTERNOON_START);
    const afternoonEndMinutes = timeToMinutes(AFTERNOON_END);
    
    // If no time in and out, consider absent for afternoon (4 hours = 240 minutes)
    if (!timeIn && !timeOut) return 240;
    
    // If only time in exists, consider half day absent
    if (timeIn && !timeOut) return 240;
    
    // If only time out exists, consider half day absent
    if (!timeIn && timeOut) return 240;
    
    const actualTimeIn = timeToMinutes(timeIn);
    const actualTimeOut = timeToMinutes(timeOut);
    
    // Calculate tardiness (late arrival)
    if (actualTimeIn > afternoonStartMinutes) {
        total += actualTimeIn - afternoonStartMinutes;
    }
    
    // Calculate undertime (early departure)
    if (actualTimeOut < afternoonEndMinutes) {
        total += afternoonEndMinutes - actualTimeOut;
    }
    
    return total;
}

/**
 * Calculates total tardiness and undertime for a day
 * @param {Object} times - Object containing all time entries for the day
 * @returns {number} Total minutes of tardiness and undertime
 */
function calculateTotalTU(times) {
    // If no times at all, return full day absent (8 hours = 480 minutes)
    if (!times.timeInAm && !times.timeOutAm && !times.timeInPm && !times.timeOutPm) {
        return TOTAL_REQUIRED_MINUTES;
    }
    
    const morningTU = calculateMorningTU(times.timeInAm, times.timeOutAm);
    const afternoonTU = calculateAfternoonTU(times.timeInPm, times.timeOutPm);
    
    return morningTU + afternoonTU;
}

// Function to update all TU calculations in the form
function updateAllTUCalculations() {
    const rows = document.querySelectorAll('[id^="logs-time-in-am-"]');
    rows.forEach((row, index) => {
        const timeInAm = document.getElementById(`logs-time-in-am-${index}`).value;
        const timeOutAm = document.getElementById(`logs-time-out-am-${index}`).value;
        const timeInPm = document.getElementById(`logs-time-in-pm-${index}`).value;
        const timeOutPm = document.getElementById(`logs-time-out-pm-${index}`).value;
        
        const totalTU = calculateTotalTU({
            timeInAm,
            timeOutAm,
            timeInPm,
            timeOutPm
        });
        
        // Update the total field
        const totalField = document.getElementById(`logs-total-tardiness-${index}`);
        if (totalField) {
            totalField.value = totalTU === 0 ? '0' : totalTU;
        }
    });
}

// Export functions for use in other files
window.TimeUtils = {
    calculateTotalTU,
    updateAllTUCalculations
};

// Add event listeners to all time input fields
document.addEventListener('DOMContentLoaded', function() {
    // Initial calculation
    updateAllTUCalculations();
    
    // Add event listeners to time input fields
    document.querySelectorAll('input[type="time"]').forEach(input => {
        input.addEventListener('change', updateAllTUCalculations);
    });
});
