<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    if (empty($_POST['theme_name'])) {
        die('Theme name is required');
    }

    $REPLACEMENTS = [
        '{{THEME_NAME}}' => htmlspecialchars($_POST['theme_name']),
        '{{THEME_NAME__LOWERCASE}}' => sanitize_title($_POST['theme_name']),
        '{{THEME_NAME__UPPERCASE}}' => strtoupper(sanitize_title($_POST['theme_name'])),
        '{{THEME_URI}}' => $_POST['theme_uri'],
        '{{AUTHOR_NAME}}' => htmlspecialchars($_POST['author_name']),
        '{{AUTHOR_URI}}' => $_POST['author_uri'],
        '{{THEME_DESCRIPTION}}' => htmlspecialchars($_POST['theme_description']),
        '{{THEME_TAGS}}' => htmlspecialchars($_POST['theme_tags']),
        '{{TODAY_DATE}}' => date('d M, Y'),
    ];

    $themeSlug = $REPLACEMENTS['{{THEME_NAME__LOWERCASE}}'];
    $zipFilename = $themeSlug . '.zip';

    $stubsFolder = realpath(__DIR__ . '/stubs') . DIRECTORY_SEPARATOR;
    $tempDir = realpath(__DIR__ . '/temp') . DIRECTORY_SEPARATOR . $themeSlug;

    // Create temporary directory
    if (file_exists($tempDir)) {
        delete_folder($tempDir);
    }

    if (!mkdir($tempDir, 0755, true)) {
        die('Failed to create temporary directory: ' . $tempDir);
    }

    // Process stub files
    $di = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stubsFolder, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($di as $file) {
        if ($file->isDir())
            continue;

        $filePath = $file->getRealPath();

        // Get relative path using iterator's method
        $relativePath = $di->getSubPathname();
        $relativePath = str_replace(['theme', '.stub'], [$themeSlug, ''], $relativePath);

        // Normalize path separators
        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $newPath = $tempDir . DIRECTORY_SEPARATOR . $relativePath;

        // Create directories
        $dirPath = dirname($newPath);
        if (!file_exists($dirPath) && !mkdir($dirPath, 0755, true)) {
            die("Failed to create directory: " . htmlspecialchars($dirPath));
        }

        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'stub') {
            // Process .stub files
            $content = file_get_contents($filePath);
            $content = str_replace(
                array_keys($REPLACEMENTS),
                array_values($REPLACEMENTS),
                $content
            );

            if (file_put_contents($newPath, $content) === false) {
                die("Failed to write file: $newPath");
            }
        } else {
            // Copy non-stub files directly
            if (file_exists($filePath) && !copy($filePath, $newPath)) {
                die("Failed to copy file: $filePath");
            }
        }
    }

    // Add special files with proper path handling
    $specialFiles = [
        'screenshot.png' => $tempDir . DIRECTORY_SEPARATOR . 'screenshot.png',
        'languages' . DIRECTORY_SEPARATOR . 'theme.pot' =>
            $tempDir . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR . $themeSlug . '.pot'
    ];

    foreach ($specialFiles as $source => $dest) {
        $sourcePath = $stubsFolder . $source;
        if (file_exists($sourcePath)) {
            $destDir = dirname($dest);

            if (!file_exists($destDir)) {
                mkdir($destDir, 0755, true);
            }

            if (!copy($sourcePath, $dest)) {
                die("Failed to copy special file: $sourcePath");
            }
        }
    }

    // Create ZIP archive
    $zip = new ZipArchive();
    if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die('Cannot create zip file');
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $tempDir,
            RecursiveDirectoryIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($tempDir) + 1);

        // Normalize directory separators for ZIP
        $relativePath = str_replace('\\', '/', $relativePath);

        if ($file->isDir()) {
            // Add empty directories
            $zip->addEmptyDir($relativePath);
        } else {
            // Add file with proper path
            $zip->addFile($filePath, $relativePath);
        }
    }

    if (!$zip->close()) {
        die('Failed to create ZIP archive');
    }

    // Verify ZIP file integrity
    if (!file_exists($zipFilename)) {
        die('ZIP file creation failed');
    }

    // Send to browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zipFilename) . '"');
    header('Content-Length: ' . filesize($zipFilename));
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    readfile($zipFilename);

    // Cleanup
    unlink($zipFilename);

    // Delete temporary directory
    delete_folder($tempDir);
}

// Sanitize title
function sanitize_title($title)
{
    return strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', $title));
}

// Delete folder
function delete_folder($basePath)
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    // Finally, remove the b
    return rmdir($basePath);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Theme Boilerplate by Shahin Moyshan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Trebuchet MS', 'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Arial, sans-serif;
            font-size: 18px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 60px 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .row {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            margin: 0 -10px;
        }

        .column {
            flex: 1;
            margin: 10px;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px 16px;
            margin: 5px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            background-color: #4CAF50;
            border: none;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <div class="container">

        <h2>WordPress Theme Boilerplate</h2>

        <form method="post">
            <div class="row">
                <div class="column">
                    <label for="theme-name">Theme Name</label>
                    <input required type="text" name="theme_name" id="theme-name">
                </div>
                <div class="column">
                    <label for="theme-uri">Theme URI</label>
                    <input required type="text" name="theme_uri" id="theme-uri">
                </div>
            </div>
            <div class="row">
                <div class="column">
                    <label for="author-name">Author Name</label>
                    <input required type="text" name="author_name" id="author-name">
                </div>
                <div class="column">
                    <label for="author-uri">Author URI</label>
                    <input required type="text" name="author_uri" id="author-uri">
                </div>
            </div>
            <div>
                <label for="theme-description">Description</label>
                <textarea required name="theme_description" id="theme-description"></textarea>
            </div>
            <div>
                <label for="theme-tags">Tags</label>
                <textarea required name="theme_tags" id="theme-tags" placeholder="tag1, tag2, tag3"></textarea>
            </div>
            <br>
            <button type="submit">Generate</button>
        </form>

    </div>

</body>

</html>