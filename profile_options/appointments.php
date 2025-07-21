<?php
require_once(__DIR__ . '/../init.php');

// Handle file upload and instant file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Instant badge/file delete via AJAX (JSON request) ---
    // Check for JSON body with delete_file
    $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!empty($input['delete_file']) && !empty($input['filename'])) {
            $filename = $input['filename'];
            $userid = $input['userid'] ?? '';
            $response = ['success' => false, 'message' => ''];
            $upload_path = __DIR__ . '/../assets/appt_img/';
            $file_path = $upload_path . $filename;
            if ($filename && file_exists($file_path)) {
                if (unlink($file_path)) {
                    $response['success'] = true;
                    $response['message'] = 'File deleted successfully';
                } else {
                    $response['message'] = 'Failed to delete file';
                }
            } else {
                $response['message'] = 'File not found';
            }
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }

    // --- File upload handler ---
    $userid = $_POST['userid'] ?? null;
    $experience_ids = $_POST['experience_ids'] ?? [];
    $response = ['success' => false, 'message' => ''];

    if (!$userid || empty($experience_ids)) {
        $response['message'] = 'Invalid request parameters';
        echo json_encode($response);
        exit;
    }

    // Initialize variables for file handling
    $upload_path = __DIR__ . '/../assets/appt_img/';
    if (!file_exists($upload_path)) {
        mkdir($upload_path, 0777, true);
    }

    // Get position titles for each experience ID
    $position_titles = [];
    $stmt = $pdo->prepare("SELECT id, position_title FROM work_experience_mssd WHERE id = ? AND userid = ?");
    foreach ($experience_ids as $exp_id) {
        $stmt->execute([$exp_id, $userid]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $position_titles[$exp_id] = $result['position_title'];
        }
    }

    $successfully_uploaded = [];
    $errors = [];

    // Process each experience's files
    foreach ($_FILES['files']['name'] as $exp_index => $files) {
        if (!isset($experience_ids[$exp_index])) continue;
        $exp_id = $experience_ids[$exp_index];
        if (!isset($position_titles[$exp_id])) continue;

        // Get the position title for this experience
        $position_title = $position_titles[$exp_id];
        
        // Handle multiple files for this experience
        foreach ($files as $key => $filename) {
            if ($_FILES['files']['error'][$exp_index][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['files']['tmp_name'][$exp_index][$key];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // Only allow jpg/jpeg files
                if (!in_array($ext, ['jpg', 'jpeg'])) {
                    $errors[] = "File type not allowed: $filename. Only JPG files are accepted.";
                    continue;
                }

                // Find next available number for this userid and position
                $counter = 1;
                do {
                    $new_filename = sprintf('%d_%s_%d.%s', 
                        $userid, 
                        str_replace(' ', '_', strtoupper($position_title)),
                        $counter,
                        $ext
                    );
                    $counter++;
                } while (file_exists($upload_path . $new_filename));

                // Try to move the uploaded file
                if (move_uploaded_file($tmp_name, $upload_path . $new_filename)) {
                    $successfully_uploaded[] = $new_filename;
                } else {
                    $errors[] = "Failed to save file: $filename";
                }
            }
        }
    }

    // Prepare response message
    $messages = [];
    if (!empty($successfully_uploaded)) {
        $messages[] = 'Files uploaded successfully';
    }
    
    $error_messages = $errors;
    
    $response['success'] = empty($errors);
    $response['message'] = empty($error_messages)
        ? implode(', ', $messages)
        : 'Some operations failed: ' . implode(', ', $error_messages);
    $response['uploaded'] = $successfully_uploaded;
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$userid = isset($_GET['userid']) && is_numeric($_GET['userid']) ? intval($_GET['userid']) : null;
$experiences = [];
$position_images = [];

// Get work experience data with unique position titles
if ($userid) {
    // Use MIN(id) to get one consistent id per position_title
    $stmt = $pdo->prepare("
        SELECT MIN(id) as id, position_title 
        FROM work_experience_mssd 
        WHERE userid = ? 
        GROUP BY position_title
    ");
    $stmt->execute([$userid]);
    $experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get existing images for each position
    $upload_path = __DIR__ . '/../assets/appt_img/';
    foreach ($experiences as $exp) {
        $position_prefix = $userid . '_' . str_replace(' ', '_', strtoupper($exp['position_title'])) . '_';
        $position_images[$exp['id']] = [];
        
        if (is_dir($upload_path)) {
            // Get all files that match the exact position pattern
            $pattern = $upload_path . $position_prefix . '[0-9]+\.(?:jpg|jpeg)$';
            $files = glob($upload_path . '*', GLOB_BRACE);
            if ($files) {
                foreach ($files as $file) {
                    if (preg_match('#^' . preg_quote($upload_path . $position_prefix) . '[0-9]+\.(?:jpg|jpeg)$#i', $file)) {
                        $position_images[$exp['id']][] = basename($file);
                    }
                }
                sort($position_images[$exp['id']]); // Sort files numerically
            }
        }
    }
}
?>
<div id="hs-appointments-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-appointments-modal-label">
  <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-14 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
    <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70">
      <!-- Modal Header -->
      <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="hs-appointments-modal-label" class="font-bold text-gray-800 dark:text-white">Appointments</h3>
        <button type="button" class="modal-close-icon size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close">
          <span class="sr-only">Close</span>
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-4 overflow-y-auto">
        <form id="appointments-upload-form" autocomplete="off" method="POST" enctype="multipart/form-data">
          <?php if ($experiences): ?>
            <?php foreach ($experiences as $idx => $exp): ?>
              <div class="grid grid-cols-12 gap-4 items-start<?= $idx > 0 ? ' mt-4' : '' ?>">
                <div class="col-span-9">
                  <div class="flex flex-col">
                    <div class="block text-sm font-normal text-gray-500 dark:text-neutral-500 mb-2">
                      <?= htmlspecialchars(strtoupper($exp['position_title'])) ?>
                    </div>
                    <?php if (!empty($position_images[$exp['id']])): ?>
                      <div class="flex flex-wrap gap-2 mb-2 appointment-badges" style="display: none;">
                        <?php foreach ($position_images[$exp['id']] as $image): ?>
                          <span class="inline-flex items-center gap-x-1.5 py-1.5 ps-3 pe-2 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800/30 dark:text-blue-500">
                            <?= basename($image) ?>
                            <button type="button" class="shrink-0 size-4 inline-flex items-center justify-center rounded-full hover:bg-blue-200 focus:outline-hidden focus:bg-blue-200 focus:text-blue-500 dark:hover:bg-blue-900 badge-remove-btn" data-file="<?= htmlspecialchars($image) ?>" data-expid="<?= htmlspecialchars($exp['id']) ?>">
                              <span class="sr-only">Remove badge</span>
                              <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18"></path>
                                <path d="m6 6 12 12"></path>
                              </svg>
                            </button>
                          </span>
                        <?php endforeach; ?>
                      </div>
                      <div class="file-count-text">
                        <span class="text-xs text-gray-500 dark:text-neutral-400"><?= count($position_images[$exp['id']]) ?> file(s) attached</span>
                      </div>
                    <?php endif; ?>
                    <label for="appointment-attachments-<?= $idx ?>" class="hidden">Choose files for <?= htmlspecialchars($exp['position_title']) ?></label>
                    <input type="file"
                      name="files[<?= $idx ?>][]"
                      id="appointment-attachments-<?= $idx ?>"
                      accept=".jpeg, .jpg"
                      multiple
                      class="block w-full border border-gray-200 rounded-lg text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400
                      file:bg-gray-50 file:border-0
                      file:bg-gray-100 file:py-2 file:px-4
                      dark:file:bg-neutral-700 dark:file:text-neutral-400"
                      style="display:none;">
                    <input type="hidden" name="experience_ids[]" value="<?= htmlspecialchars($exp['id']) ?>">
                  </div>
                </div>
                <div class="col-span-3 flex justify-end">
                  <?php if (!empty($position_images[$exp['id']])): ?>
                    <div class="hs-dropdown relative inline-flex show-on-load" style="display: inline-flex;">
                      <button type="button"
                        class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                        aria-label="Download Attachments">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                          stroke-width="2" stroke="currentColor" class="size-4">
                          <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16 12l-4 4m0 0l-4-4m4 4V4m4 14H8" />
                        </svg>
                      </button>
                      <div class="hs-dropdown-menu hidden transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 min-w-60 bg-white shadow-md rounded-lg p-2 mt-2 dark:bg-neutral-900 dark:border dark:border-neutral-700 dark:divide-neutral-700 left-0 right-auto">
                        <?php foreach ($position_images[$exp['id']] as $image): ?>
                          <a href="/pulse/assets/appt_img/<?= htmlspecialchars($image) ?>"
                             download
                             class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300">
                            <?= htmlspecialchars($image) ?>
                          </a>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <button type="button"
                      class="flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-400 shadow-2xs disabled:opacity-50 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-600"
                      disabled
                      aria-label="No attachments">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16 12l-4 4m0 0l-4-4m4 4V4m4 14H8" />
                      </svg>
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <input type="hidden" name="userid" value="<?= htmlspecialchars($userid) ?>">
          <?php else: ?>
            <div class="p-2 text-gray-500">No appointments found for this user.</div>
          <?php endif; ?>
        </form>
      </div>
      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <button type="button" class="modal-edit-btn py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-gray-700 dark:focus:bg-gray-700">
          Edit
        </button>
        <button type="button" class="modal-close-btn py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-gray-700 dark:focus:bg-gray-700" style="display:none;">
          Close
        </button>
        <button type="button" class="modal-save-btn py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none" style="display:none;">
          Save changes
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit button click
    const editBtn = document.querySelector('.modal-edit-btn');
    editBtn.addEventListener('click', function() {
        // Hide download buttons
        document.querySelectorAll('.hs-dropdown.show-on-load').forEach(el => {
            el.style.display = 'none';
        });
    });

    // Handle remove badge buttons
    document.querySelectorAll('[data-file]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const badge = this.closest('span');
            const filename = this.getAttribute('data-file');
            if (confirm('Are you sure you want to remove this file?')) {
                // TODO: Add AJAX call to delete the file
                badge.remove();
            }
        });
    });
});
</script>