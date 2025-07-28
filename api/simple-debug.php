<?php
// simple-debug.php - Very basic debug to check what's working

ob_start();
header('Content-Type: application/json');

try {
    $debug = [];
    $debug['step1'] = 'Started';
    
    // Check if basic PHP is working
    $debug['php_version'] = phpversion();
    $debug['step2'] = 'PHP working';
    
    // Check if ZipArchive class exists
    $debug['zip_class_exists'] = class_exists('ZipArchive');
    $debug['step3'] = 'Checked ZipArchive';
    
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $debug['zip_object_created'] = true;
        $debug['step4'] = 'ZipArchive object created';
        
        // Try to create a simple zip
        $tempFile = sys_get_temp_dir() . '/test.zip';
        $result = $zip->open($tempFile, ZipArchive::CREATE);
        $debug['zip_open_result'] = $result;
        $debug['zip_open_success'] = ($result === TRUE);
        $debug['step5'] = 'Zip open attempted';
        
        if ($result === TRUE) {
            $zip->addFromString('test.txt', 'Hello World');
            $zip->close();
            $debug['zip_created'] = file_exists($tempFile);
            $debug['zip_size'] = file_exists($tempFile) ? filesize($tempFile) : 0;
            
            // Clean up
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            $debug['step6'] = 'Zip test completed';
        } else {
            $debug['zip_error'] = 'Failed to open zip file';
            $debug['step6'] = 'Zip test failed';
        }
    } else {
        $debug['error'] = 'ZipArchive class not available';
    }
    
    $debug['final_step'] = 'Debug completed successfully';
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode($debug, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
} catch (Error $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['error' => 'PHP Error: ' . $e->getMessage()]);
}
?>