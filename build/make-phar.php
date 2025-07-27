<?php
declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php make-phar.php <phar_name> <git_hash>\n");
    exit(1);
}

$pharName = $argv[1];
$gitHash = $argv[2];

define('PHAR_FILE', __DIR__ . '/../' . $pharName . '.phar');
const VIRION_FILE = __DIR__ . '/../libasynql/virion.yml';
const SRC_DIR = __DIR__ . '/../libasynql/src';

// Add debugging information
echo "PHAR file path: " . PHAR_FILE . "\n";
echo "Plugin file path: " . VIRION_FILE . "\n";
echo "Source directory: " . SRC_DIR . "\n";

// Check if PHAR creation is enabled
if (!Phar::canWrite()) {
    fwrite(STDERR, "Error: PHAR creation is disabled. Set phar.readonly=0 in php.ini\n");
    exit(1);
}

// Check if directories exist
if (!is_dir(SRC_DIR)) {
    fwrite(STDERR, "Error: Source directory does not exist: " . SRC_DIR . "\n");
    exit(1);
}

if (!file_exists(VIRION_FILE)) {
    fwrite(STDERR, "Warning: Virion file does not exist: " . VIRION_FILE . "\n");
}

/**
 * Creates a PHAR file and adds files from the source and resource directories.
 *
 * @param string $gitHash The git hash to include in the PHAR metadata.
 */
function createPhar(string $gitHash): void {
    if (file_exists(PHAR_FILE)) {
        echo "Removing existing PHAR file: " . PHAR_FILE . "\n";
        unlink(PHAR_FILE);
    }

    try {
        $phar = new Phar(PHAR_FILE);
        $phar->startBuffering();
        
        // Set a more generic stub since the specific path might not exist
        $phar->setStub("<?php __HALT_COMPILER(); ?>");
        
        $filesAdded = addFilesToPhar($phar, SRC_DIR, "src/");
        echo "Added $filesAdded files from source directory\n";
        
        addFileToPhar($phar, VIRION_FILE, "virion.yml");
        
        $phar->setMetadata([
            'git_hash' => $gitHash,
            'build_date' => date('Y-m-d H:i:s'),
        ]);
        
        $phar->stopBuffering();
        
        // Verify the file was created
        if (file_exists(PHAR_FILE)) {
            echo "Successfully created " . PHAR_FILE . " (" . filesize(PHAR_FILE) . " bytes)\n";
        } else {
            fwrite(STDERR, "Error: PHAR file was not created\n");
            exit(1);
        }
        
    } catch (Exception $e) {
        fwrite(STDERR, "Failed to create " . PHAR_FILE . ": " . $e->getMessage() . "\n");
        fwrite(STDERR, "Error details: " . $e->getFile() . ":" . $e->getLine() . "\n");
        exit(1);
    }
}

/**
 * Adds all files from a directory to a PHAR file.
 *
 * @param Phar $phar The PHAR object.
 * @param string $directory The directory to add files from.
 * @param string $prefix Optional prefix for the file paths in the PHAR.
 * @return int Number of files added
 */
function addFilesToPhar(Phar $phar, string $directory, string $prefix = ''): int {
    if (!is_dir($directory)) {
        echo "Warning: Directory does not exist: $directory\n";
        return 0;
    }

    $filesAdded = 0;
    $directoryIterator = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($directoryIterator);

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            // Fix path separator handling for cross-platform compatibility
            $relativePath = $prefix . str_replace(
                [realpath($directory) . DIRECTORY_SEPARATOR, '\\'], 
                ['', '/'], 
                $file->getRealPath()
            );
            
            try {
                $phar->addFile($file->getRealPath(), $relativePath);
                $filesAdded++;
                echo "Added: $relativePath\n";
            } catch (Exception $e) {
                fwrite(STDERR, "Warning: Failed to add file {$file->getRealPath()}: {$e->getMessage()}\n");
            }
        }
    }
    
    return $filesAdded;
}

/**
 * Adds a single file to the PHAR if it exists.
 *
 * @param Phar $phar The PHAR object.
 * @param string $filePath The path to the file.
 * @param string $pharPath The path in the PHAR.
 */
function addFileToPhar(Phar $phar, string $filePath, string $pharPath): void {
    if (file_exists($filePath)) {
        try {
            $phar->addFile($filePath, $pharPath);
            echo "Added single file: $pharPath\n";
        } catch (Exception $e) {
            fwrite(STDERR, "Warning: Failed to add file $filePath: {$e->getMessage()}\n");
        }
    } else {
        echo "Warning: File does not exist: $filePath\n";
    }
}

createPhar($gitHash);