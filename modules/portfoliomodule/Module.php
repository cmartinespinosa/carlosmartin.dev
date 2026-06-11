<?php
namespace modules\portfoliomodule;

use Craft;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/portfoliomodule', __DIR__);
        parent::init();

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\portfoliomodule\\console\\controllers';
        }

        $this->set('portfolioService', \modules\portfoliomodule\services\PortfolioService::class);
    }
}
