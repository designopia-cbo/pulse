<?php
require_once(__DIR__ . '/../init.php');

$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = date('Y');

// Fetch tardiness data, join employee fullname, for selected month
$stmt = $pdo->prepare("
    SELECT t.total_tardiness, e.fullname
    FROM tardiness t
    JOIN employee e ON t.userid = e.id
    WHERE MONTH(t.start_date) = :month AND YEAR(t.start_date) = :year
    ORDER BY t.total_tardiness DESC
    LIMIT 10
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
        <td colspan="2" class="px-6 py-3 text-sm text-gray-500 dark:text-neutral-400 text-center">No data found for this month.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>