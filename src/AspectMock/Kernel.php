<?php
namespace AspectMock;

use AspectMock\Core\Registry;
use AspectMock\Intercept\BeforeMockTransformer;
use Lanfix\Core\AspectContainer;
use Lanfix\Core\AspectKernel;
use Lanfix\Instrument\ClassLoading\SourceTransformingLoader;
use Lanfix\Instrument\Transformer\CachingTransformer;
use Lanfix\Instrument\Transformer\FilterInjectorTransformer;
use Lanfix\Instrument\Transformer\MagicConstantTransformer;
use Symfony\Component\Finder\Finder;

require_once __DIR__ . '/Core/Registry.php';

class Kernel extends AspectKernel
{
    public function init(array $options = []): void
    {
        if (!isset($options['excludePaths'])) {
            $options['excludePaths'] = [];
        } elseif (!is_array($options['excludePaths'])) {
            $options['excludePaths'] = [ $options['excludePaths'] ];
        }
        $options['debug'] = true;
        $options['excludePaths'][] = __DIR__;

        parent::init($options);
    }

    protected function configureAop(AspectContainer $container)
    {
        Registry::setMocker(new Core\Mocker);
    }

    /**
     * Scans a directory provided and includes all PHP files from it.
     * All files will be parsed and aspects will be added.
     *
     * @param $dir
     */
    public function loadPhpFiles($dir)
    {
        $files = Finder::create()->files()->name('*.php')->in($dir);
        foreach ($files as $file) {
            $this->loadFile($file->getRealpath());
        }

    }

    /**
     * Includes file and injects aspect pointcuts into int
     *
     * @param $file
     */
    public function loadFile($file)
    {
        include FilterInjectorTransformer::rewrite($file);
    }

    protected function registerTransformers(): array
    {
        $cachePathManager = $this->getContainer()->get('aspect.cache.path.manager');;

        $sourceTransformers = [
            new FilterInjectorTransformer($this, SourceTransformingLoader::getId(), $cachePathManager),
            new MagicConstantTransformer($this),
            new BeforeMockTransformer(
                $this,
                $this->getContainer()->get('aspect.advice_matcher'),
                $cachePathManager,
                $this->getContainer()->get('aspect.cached.loader')
            )
        ];

        return [
            new CachingTransformer($this, $sourceTransformers, $cachePathManager)
        ];
    }
}

require __DIR__ . '/Intercept/before_mock.php';