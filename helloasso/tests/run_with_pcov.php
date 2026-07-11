<?php
/**
 * Script simplifié pour exécuter les tests avec couverture via PCOV
 * Utilise php-code-coverage (installé avec phpcov) pour générer les rapports
 * 
 * Usage:
 *   php tests/run_with_pcov.php [format]
 * 
 * Formats: html, text, clover, cobertura, xml
 */

// Bufferisation TOUT AU DÉBUT
ob_start();

require __DIR__ . '/../vendor/autoload.php';

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\PcovDriver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\Clover as CloverReport;
use SebastianBergmann\CodeCoverage\Report\Cobertura as CoberturaReport;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;
use SebastianBergmann\CodeCoverage\Report\Text as TextReport;
use SebastianBergmann\CodeCoverage\Report\Xml\Facade as XmlReport;
use SebastianBergmann\CodeCoverage\Report\Thresholds;

// Configuration
$format = $_SERVER['argv'][1] ?? 'html';
$outputDir = __DIR__ . '/coverage';

// Créer le filtre pour inclure uniquement lib/
$filter = new Filter();
$libDir = __DIR__ . '/../lib';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($libDir)
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filter->includeFile($file->getRealPath());
    }
}

// Créer le driver et le collecteur
$driver = new PcovDriver($filter);
$coverage = new CodeCoverage($driver, $filter);

// Démarrer la collecte
$coverage->start('HelloAsso Order Tests');

// Charger bootstrap et _inc.php SANS afficher de sortie
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/_inc.php';

// Exécuter les tests
$test_files = glob(__DIR__ . '/Order/0*.php');
sort($test_files);

foreach ($test_files as $file) {
    try {
        require $file;
    } catch (\Throwable $e) {
        // Ne pas afficher directement
    }
}

// Arrêter la collecte
$coverage->stop();
$report = $coverage->getReport();

// Générer le rapport
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$thresholds = Thresholds::default();

switch (strtolower($format)) {
    case 'html':
        (new HtmlReport('', null, $thresholds))->process($report, $outputDir);
        echo "Rapport HTML: " . str_replace('/workspaces/', '/mistral-playground/', $outputDir) . "/index.html\n";
        break;

    case 'clover':
        $cloverXml = (new CloverReport($thresholds))->process($report);
        file_put_contents($outputDir . '/clover.xml', $cloverXml);
        echo "Rapport Clover: " . str_replace('/workspaces/', '/mistral-playground/', $outputDir) . "/clover.xml\n";
        break;

    case 'cobertura':
        $coberturaXml = (new CoberturaReport($thresholds))->process($report);
        file_put_contents($outputDir . '/cobertura-coverage.xml', $coberturaXml);
        echo "Rapport Cobertura: " . str_replace('/workspaces/', '/mistral-playground/', $outputDir) . "/cobertura-coverage.xml\n";
        break;

    case 'xml':
        (new XmlReport())->process($outputDir, $report, []);
        echo "Rapport XML: " . str_replace('/workspaces/', '/mistral-playground/', $outputDir) . "/\n";
        break;

    case 'text':
    default:
        $textReport = (new TextReport($thresholds))->process($report);
        file_put_contents($outputDir . '/coverage.txt', $textReport);
        echo "Rapport texte: " . str_replace('/workspaces/', '/mistral-playground/', $outputDir) . "/coverage.txt\n";
        break;
}

// Flush le buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
echo "Couverture terminée!\n";
