<?php
require_once(__DIR__ . '/../init.php');

// Get selected month and year (from AJAX or default to current)
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// For year dropdown: get all years in tardiness table
$yearsStmt = $pdo->query("SELECT DISTINCT YEAR(start_date) as year FROM tardiness ORDER BY year DESC");
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

// For month enabling: get all months with data for the selected year
$monthsWithDataStmt = $pdo->prepare("SELECT DISTINCT MONTH(start_date) as month FROM tardiness WHERE YEAR(start_date) = :year");
$monthsWithDataStmt->execute([':year' => $year]);
$monthsWithData = array_map('intval', $monthsWithDataStmt->fetchAll(PDO::FETCH_COLUMN));

// For year enabling: get all years with data for the selected month
$yearsWithDataStmt = $pdo->prepare("SELECT DISTINCT YEAR(start_date) as year FROM tardiness WHERE MONTH(start_date) = :month");
$yearsWithDataStmt->execute([':month' => $month]);
$yearsWithData = array_map('intval', $yearsWithDataStmt->fetchAll(PDO::FETCH_COLUMN));

$monthNames = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
?>

<div class="mt-6">
  <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800 p-4 md:p-5 min-h-[100px]">
    <div class="relative flex flex-col gap-4 md:flex-row md:items-center mb-5">
      <div class="w-12 h-12 flex items-center justify-center rounded-lg bg-blue-600 text-white">
        <svg class="shrink-0 size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10" />
          <polyline points="12 6 12 12 16 14" />
        </svg>
      </div>
      <div>
        <h6 class="block font-sans text-base font-semibold leading-relaxed tracking-normal text-blue-gray-900 antialiased dark:text-neutral-200">
          Tardiness Tracker
        </h6>
        <p class="block font-sans text-sm font-normal leading-normal text-gray-700 antialiased dark:text-neutral-400">
          List of employees with most tardiness for the selected month and year.
        </p>
      </div>
    </div>

    <!-- Dropdowns row -->
    <div class="mb-4 flex flex-col md:flex-row gap-4">
      <!-- Month Dropdown -->
      <div class="flex-1">
        <select id="tardiness-month" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
          <?php foreach ($monthNames as $num => $label): ?>
            <option value="<?= $num ?>" 
              <?= ($num == $month ? "selected" : "") ?>
              <?= in_array($num, $monthsWithData) ? "" : "disabled" ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Year Dropdown -->
      <div class="flex-1">
        <select id="tardiness-year" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
          <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>"
              <?= ($y == $year ? "selected" : "") ?>
              <?= in_array($y, $yearsWithData) ? "" : "disabled" ?>>
              <?= $y ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <!-- End Dropdowns row -->

    <div id="tardiness-table-container">
      <?php
      // Fetch ONLY top 5 by total_tardiness for selected month and year
      $stmt = $pdo->prepare("
          SELECT t.total_tardiness, e.fullname
          FROM tardiness t
          JOIN employee e ON t.userid = e.id
          WHERE MONTH(t.start_date) = :month AND YEAR(t.start_date) = :year
          ORDER BY t.total_tardiness DESC
          LIMIT 5
      ");
      $stmt->execute([
          ':month' => $month,
          ':year' => $year
      ]);
      $tardyList = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
        <thead class="bg-gray-50 dark:bg-neutral-800">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-600 dark:text-neutral-300 whitespace-nowrap">
              Full Name
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-600 dark:text-neutral-300 whitespace-nowrap">
              Total Tardiness (Minutes)
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
          <?php if ($tardyList): ?>
            <?php foreach ($tardyList as $row): ?>
              <tr>
                <td class="px-6 py-3 text-sm text-gray-800 dark:text-neutral-200"><?= htmlspecialchars($row['fullname']) ?></td>
                <td class="px-6 py-3 text-sm text-gray-800 dark:text-neutral-200"><?= (int)$row['total_tardiness'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="2" class="px-6 py-3 text-sm text-gray-500 dark:text-neutral-400 text-center">No data found for this month and year.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function refreshTardinessDropdowns(changed) {
  // On month or year change, reload the tracker with both values
  const month = document.getElementById('tardiness-month').value;
  const year = document.getElementById('tardiness-year').value;
  fetch('charts/tardiness_tracker.php?month=' + encodeURIComponent(month) + '&year=' + encodeURIComponent(year))
    .then(response => response.text())
    .then(html => {
      // Replace the whole tracker card (including dropdowns)
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const trackerDiv = doc.querySelector('.mt-6');
      if (trackerDiv) {
        document.querySelector('.mt-6').replaceWith(trackerDiv);
      }
    });
}
document.addEventListener('DOMContentLoaded', function(){
  document.getElementById('tardiness-month').addEventListener('change', function() {
    refreshTardinessDropdowns('month');
  });
  document.getElementById('tardiness-year').addEventListener('change', function() {
    refreshTardinessDropdowns('year');
  });
});
</script>