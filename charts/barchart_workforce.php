<?php
require_once(__DIR__ . '/../init.php');

// --- BEGIN: Dynamic Plantilla Positions Data for Chart ---

// Get all unique offices for pstatus=1
$officeStmt = $pdo->prepare("SELECT DISTINCT office FROM plantilla_position WHERE pstatus = 1 ORDER BY office ASC");
$officeStmt->execute();
$officeList = $officeStmt->fetchAll(PDO::FETCH_COLUMN);

// Prepare data for both 'vacant' and 'filled', so frontend logic is simple
$vacantCounts = [];
$filledCounts = [];
foreach ($officeList as $office) {
    // Vacant: userid IS NULL
    $vacantStmt = $pdo->prepare("SELECT COUNT(*) FROM plantilla_position WHERE pstatus = 1 AND office = :office AND userid IS NULL");
    $vacantStmt->bindParam(':office', $office, PDO::PARAM_STR);
    $vacantStmt->execute();
    $vacantCounts[] = (int)$vacantStmt->fetchColumn();

    // Filled: userid IS NOT NULL
    $filledStmt = $pdo->prepare("SELECT COUNT(*) FROM plantilla_position WHERE pstatus = 1 AND office = :office AND userid IS NOT NULL");
    $filledStmt->bindParam(':office', $office, PDO::PARAM_STR);
    $filledStmt->execute();
    $filledCounts[] = (int)$filledStmt->fetchColumn();
}

// Pass chart data to JS (safe encoding)
$officeLabels = json_encode($officeList);
$vacantData = json_encode($vacantCounts);
$filledData = json_encode($filledCounts);
?>

<!-- Header Icon and Info -->
<div class="relative flex flex-col gap-4 md:flex-row md:items-center">
  <div class="w-12 h-12 flex items-center justify-center rounded-lg bg-blue-600 text-white">
    <svg class="shrink-0 size-5 text-white" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
  </div>
  <div>
    <h6 class="block font-sans text-base font-semibold leading-relaxed tracking-normal text-blue-gray-900 antialiased dark:text-neutral-200">
      Workforce Distribution
    </h6>
    <p class="block max-w-sm font-sans text-sm font-normal leading-normal text-gray-700 antialiased dark:text-neutral-400">
      Employee allocation across offices and vacant positions.
    </p>
  </div>
</div>

<!-- Full-Width Dropdown Row (Now for Filled/Vacant) -->
<div>
  <select id="chart-type-select" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
    <option value="filled">Filled</option>
    <option value="vacant">Vacant</option>
  </select>
</div>

<!-- Chart Area -->
<div class="pt-0 px-2 pb-0">
  <div id="bar-chart"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
  // These values are injected from PHP
  const officeLabels = <?php echo $officeLabels; ?>;
  const vacantData = <?php echo $vacantData; ?>;
  const filledData = <?php echo $filledData; ?>;

  let currentType = 'filled';

  function getChartData(type) {
    return type === 'vacant' ? vacantData : filledData;
  }

  function getYAxisMax(data) {
    // Nice round up to next 10/20/50/100 for max
    const max = Math.max(...data, 10);
    if (max <= 20) return 20;
    if (max <= 50) return 50;
    if (max <= 100) return 100;
    if (max <= 200) return 200;
    if (max <= 500) return 500;
    return Math.ceil(max / 100) * 100;
  }

  // Initial chart config
  let chartConfig = {
    series: [
      {
        name: "Positions",
        data: getChartData(currentType),
      },
    ],
    chart: {
      type: "bar",
      height: 240,
      toolbar: {
        show: false,
      },
    },
    title: {
      show: "",
    },
    dataLabels: {
      enabled: false,
    },
    colors: ["#2563eb"],
    plotOptions: {
      bar: {
        columnWidth: "40%",
        borderRadius: 2,
      },
    },
    xaxis: {
      axisTicks: {
        show: false,
      },
      axisBorder: {
        show: false,
      },
      labels: {
        style: {
          colors: "#616161",
          fontSize: "12px",
          fontFamily: "inherit",
          fontWeight: 400,
        },
      },
      categories: officeLabels,
    },
    yaxis: {
      min: 0,
      max: getYAxisMax(getChartData(currentType)),
      tickAmount: 5,
      labels: {
        style: {
          colors: "#616161",
          fontSize: "12px",
          fontFamily: "inherit",
          fontWeight: 400,
        },
      },
    },
    grid: {
      show: true,
      borderColor: "#dddddd",
      strokeDashArray: 5,
      xaxis: {
        lines: {
          show: true,
        },
      },
      padding: {
        top: 5,
        right: 20,
      },
    },
    fill: {
      opacity: 0.8,
    },
    tooltip: {
      theme: "dark",
    },
  };

  let chart = new ApexCharts(document.querySelector("#bar-chart"), chartConfig);
  chart.render();

  // Dropdown event listener
  document.getElementById('chart-type-select').addEventListener('change', function (e) {
    currentType = e.target.value;
    const newData = getChartData(currentType);
    chart.updateOptions({
      series: [{
        name: "Positions",
        data: newData,
      }],
      yaxis: {
        min: 0,
        max: getYAxisMax(newData),
        tickAmount: 5,
        labels: {
          style: {
            colors: "#616161",
            fontSize: "12px",
            fontFamily: "inherit",
            fontWeight: 400,
          },
        },
      }
    });
  });
</script>