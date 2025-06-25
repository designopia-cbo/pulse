<?php
require_once(__DIR__ . '/../init.php');

// Helper: get week number within month (1-based)
function getWeekOfMonth($date) {
    $firstDay = (new DateTime($date))->modify('first day of this month');
    $day = (int)(new DateTime($date))->format('j');
    $weekDayOfFirst = (int)$firstDay->format('N'); // 1 (Mon) - 7 (Sun)
    return intval(floor(($day + $weekDayOfFirst - 2) / 7)) + 1;
}

// Helper: get week ranges for a month
function getMonthWeekRanges($month, $year) {
    $weeks = [];
    $dt = new DateTime("$year-$month-01");
    $last = (clone $dt)->modify('last day of this month');
    $cur = clone $dt;
    $week = 1;
    while ($cur <= $last) {
        $start = clone $cur;
        $end = (clone $cur)->modify('next Sunday');
        if ($end > $last) $end = clone $last;
        $weeks[] = [
            'week' => $week,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d')
        ];
        $cur = (clone $end)->modify('+1 day');
        $week++;
    }
    return $weeks;
}

// Current and previous month/year
$now = new DateTime('now');
$currMonth = (int)$now->format('m');
$currYear = (int)$now->format('Y');
$currLabel = $now->format('F Y');

$prevMonthObj = (clone $now)->modify('first day of this month')->modify('-1 day');
$prevMonth = (int)$prevMonthObj->format('m');
$prevYear = (int)$prevMonthObj->format('Y');
$prevLabel = $prevMonthObj->format('F Y');

// Get week ranges for current and previous month
$currWeeks = getMonthWeekRanges($currMonth, $currYear);
$prevWeeks = getMonthWeekRanges($prevMonth, $prevYear);

// Fetch audit log for current month, group by week
$currStart = $currWeeks[0]['start'] . " 00:00:00";
$currEnd = end($currWeeks)['end'] . " 23:59:59";
$stmt = $pdo->prepare("SELECT DATE(updated_at) as day, COUNT(*) as cnt FROM employee_update_history WHERE updated_at BETWEEN :start AND :end GROUP BY day");
$stmt->execute([':start' => $currStart, ':end' => $currEnd]);
$currRaw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['YYYY-MM-DD'=>cnt]

// Fetch audit log for previous month, group by week
$prevStart = $prevWeeks[0]['start'] . " 00:00:00";
$prevEnd = end($prevWeeks)['end'] . " 23:59:59";
$stmt = $pdo->prepare("SELECT DATE(updated_at) as day, COUNT(*) as cnt FROM employee_update_history WHERE updated_at BETWEEN :start AND :end GROUP BY day");
$stmt->execute([':start' => $prevStart, ':end' => $prevEnd]);
$prevRaw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['YYYY-MM-DD'=>cnt]

// Aggregate per week
function aggregateWeeks($weeks, $raw) {
    $result = [];
    foreach ($weeks as $w) {
        $total = 0;
        $period = new DatePeriod(new DateTime($w['start']), new DateInterval('P1D'), (new DateTime($w['end']))->modify('+1 day'));
        foreach ($period as $dt) {
            $dateStr = $dt->format('Y-m-d');
            $total += isset($raw[$dateStr]) ? (int)$raw[$dateStr] : 0;
        }
        $result[] = $total;
    }
    return $result;
}
$currWeekCounts = aggregateWeeks($currWeeks, $currRaw);
$prevWeekCounts = aggregateWeeks($prevWeeks, $prevRaw);

// Build week labels (always "Week 1", "Week 2", etc., up to max week in either month)
$maxWeeks = max(count($currWeeks), count($prevWeeks));
$weekLabels = [];
for ($i = 1; $i <= $maxWeeks; $i++) {
    $weekLabels[] = "Week $i";
}

// Pad data if necessary (so both arrays have same length)
while (count($currWeekCounts) < $maxWeeks) $currWeekCounts[] = 0;
while (count($prevWeekCounts) < $maxWeeks) $prevWeekCounts[] = 0;

// Prepare for JS
$chartAuditLog = [
    'previousMonth' => [
        'label' => $prevLabel,
        'data' => $prevWeekCounts
    ],
    'currentMonth' => [
        'label' => $currLabel,
        'data' => $currWeekCounts
    ],
    'categories' => $weekLabels
];
?>

<!-- Legend Indicator -->
<div class="flex justify-between items-center mb-3 sm:mb-6 -mt-0.1">
  <div>
    <h6 class="block font-sans text-base font-semibold leading-relaxed tracking-normal text-blue-gray-900 antialiased dark:text-neutral-200">
                Audit Log Chart
              </h6>
              <p class="block max-w-sm font-sans text-sm font-normal leading-normal text-gray-700 antialiased dark:text-neutral-400">
                Log Trends: Current vs Previous Month
              </p>
  </div>
  <div>
    <button id="hs-dropdown-custom-icon-trigger" type="button" class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
    <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
  <circle cx="12" cy="12" r="3"/>
</svg>
  </button>
  </div>
</div>

<!-- Chart Container -->
<div id="hs-curved-area-charts" class="w-full h-64 bg-white dark:bg-neutral-800 rounded-md"></div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const chartData = <?= json_encode($chartAuditLog) ?>;

  var options = {
    chart: {
      type: 'area',
      height: 300,
      toolbar: { show: false }
    },
    series: [
      {
        name: chartData.previousMonth.label,
        data: chartData.previousMonth.data
      },
      {
        name: chartData.currentMonth.label,
        data: chartData.currentMonth.data
      }
    ],
    xaxis: {
      categories: chartData.categories,
      labels: { style: { colors: '#9ca3af', fontSize: '13px' } }
    },
    yaxis: {
      labels: {
        style: { colors: '#9ca3af', fontSize: '13px' },
        formatter: (value) => value >= 1000 ? (value / 1000) + 'k' : value
      }
    },
    colors: ['#9333ea', '#2563eb'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    fill: {
      type: 'gradient',
      gradient: {
        type: 'vertical',
        shadeIntensity: 1,
        opacityFrom: 0.1,
        opacityTo: 0.8
      }
    },
    tooltip: {
      y: {
        formatter: (value) => value + ' updates'
      }
    }
  };

  var chartContainer = document.querySelector("#hs-curved-area-charts");
  chartContainer.innerHTML = "";
  var chart = new ApexCharts(chartContainer, options);
  chart.render();
});
</script>