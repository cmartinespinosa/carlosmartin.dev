<?php
namespace modules\portfoliomodule\console\controllers;

use Craft;
use yii\console\Controller;
use yii\console\ExitCode;

class InstallController extends Controller
{
    public function actionIndex(): int
    {
        echo "Installing Portfolio module...\n";

        $migration = new \modules\portfoliomodule\migrations\Install();
        try {
            ob_start();
            $result = $migration->safeUp();
            $output = ob_get_clean();
            if ($result === false) {
                echo "  - Install failed!\n";
                if ($output) echo $output . "\n";
                return ExitCode::UNSPECIFIED_ERROR;
            }
            echo "  - All sections, fields, and entry types created.\n";
            return ExitCode::OK;
        } catch (\Throwable $e) {
            echo "  - Error: " . $e->getMessage() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    public function actionUninstall(): int
    {
        echo "Uninstalling Portfolio module...\n";
        $migration = new \modules\portfoliomodule\migrations\Install();
        try {
            $result = $migration->safeDown();
            if ($result === false) {
                echo "  - Uninstall failed!\n";
                return ExitCode::UNSPECIFIED_ERROR;
            }
            echo "  - All Portfolio sections removed.\n";
            return ExitCode::OK;
        } catch (\Throwable $e) {
            echo "  - Error: " . $e->getMessage() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
