<?php

namespace Bx\Xhprof\Listener;

use Bitrix\Main\Application;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\EventManager;
use Bitrix\Main\Request;
use Bx\XHProf\DataListHelper;
use Bx\XHProf\DefaultChecker;
use Bx\XHProf\Interfaces\XHProfMangerInterface;
use Bx\XHProf\XHProfManager;

class XHProfListener
{
    private static ?XHProfListener $instance = null;
    private ?XHProfManager $xhprofManager = null;
    private bool $useProfiling;
    private int $timeLimit;
    private ?ModeEnum $topMode;
    private int $topLimit;
    private bool $topDiversity;
    private float $timeStart = 0;
    private float $timeEnd = 0;
    private array $profilingUrls;

    public static function getInstance(): XHProfListener
    {
        if (static::$instance === null)
        {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct()
    {
        $this->useProfiling = ConfigList::get(ConfigList::USE_PROFILING, 'N') === 'Y';
        $this->profilingUrls = ConfigList::get(ConfigList::PROFILING_URLS, []);
        $this->timeLimit = (int) ConfigList::get(ConfigList::TIME_EXEC_LIMIT, 0);
        $xhprofModeValue = (string) ConfigList::get(ConfigList::XHPROF_TOP_MODE, '');
        $this->topMode = ModeEnum::tryFrom($xhprofModeValue);
        $this->topLimit = (int) ConfigList::get(ConfigList::XHPROF_TOP_LIMIT, 0);
        $this->topDiversity = ConfigList::get(ConfigList::XHPROF_TOP_DIVERSITY, 'N') === 'Y';
    }

    private function __clone()
    {}

    public function selfRegister(?EventManager $eventManager = null): void
    {
        $eventManager = $eventManager ?? EventManager::getInstance();
        $eventManager->addEventHandler(
            'main',
            'OnPageStart',
            [XHProfListener::class, 'onStart']
        );
        $eventManager->addEventHandler(
            'main',
            'OnAfterEpilog',
            [XHProfListener::class, 'onEnd']
        );
    }

    public static function onStart(): void
    {
        $instance = static::getInstance();
        if (!$instance->isAllowProfiling()) {
            return;
        }

        $instance->timeStart = microtime(true);
        $instance->getXHProfManager()->start();
    }

    public static function onEnd(): void
    {
        $instance = static::getInstance();
        if (!$instance->isAllowProfiling()) {
            return;
        }

        $instance->timeEnd = microtime(true);
        $timeExec = $instance->timeEnd - $instance->timeStart;
        if ($timeExec < $instance->timeLimit) {
            return;
        }

        $label = $instance->createLabel();
        $data = [
            'user' => $instance->getFormattedUserInfo(),
        ];

        $xhprofManager = $instance->getXHProfManager();
        $xhprofManager->end($label, null, $data);
        $instance->clearByTopWithKey($instance->topMode->value, $instance->topLimit, $xhprofManager);
    }

    private function isAllowProfiling(): bool
    {
        if (!$this->useProfiling) {
            return false;
        }

        if (empty($this->profilingUrls)) {
            return true;
        }

        return in_array($this->getCurrentUrlWithoutParams(), $this->profilingUrls);
    }

    private function createLabel(): string
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $method = $request->getRequestMethod();
        $url = $this->getCurrentUrl($request);
        return "$method $url";
    }

    private function getCurrentUrlWithoutParams(?Request $request = null): string
    {
        $url = $this->getCurrentUrl($request);
        foreach (['?', '#'] as $separator) {
            $url = current(explode($separator, $url)) ?? '';
        }
        return (string) $url;
    }

    private function getCurrentUrl(?Request $request = null): string
    {
        $request = $request ?? Application::getInstance()->getContext()->getRequest();
        return $request->getRequestUri();
    }

    private function getFormattedUserInfo(): string
    {
        $currentUser = CurrentUser::get();
        if (empty($currentUser->getId())) {
            return 'Пользователь не авторизован';
        }

        $fio = trim(implode(
            ' ',
            [$currentUser->getLastName(), $currentUser->getFirstName(), $currentUser->getSecondName()]
        ));
        return "$fio [" . $currentUser->getId() . "]";
    }

    private function clearByTopWithKey(string $key, int $limit, ?XHProfMangerInterface $xhprofManager = null): void
    {
        if (empty($key) || $limit < 1) {
            return;
        }

        $xhprofManager = $xhprofManager ?? static::getInstance()->getXHProfManager();
        $resultList = $this->getSortedResultListByKey($key, $xhprofManager);
        if ($this->topDiversity) {
            $resultList = $this->clearDuplicatesAndGetUniqueItems($resultList, $xhprofManager);
        }

        if (count($resultList) <= $limit) {
            return;
        }

        foreach (array_slice($resultList, $limit) as $runData) {
            $this->deleteByRunData($runData, $xhprofManager);
        }
    }

    private function clearDuplicatesAndGetUniqueItems(
        array $resultList,
        ?XHProfMangerInterface $xhprofManager = null
    ): array {
        $keyMap = [];
        $newResultList = [];
        $xhprofManager = $xhprofManager ?? static::getInstance()->getXHProfManager();
        foreach ($resultList as $runData) {
            $source = (string) ($runData['source'] ?: '');
            if (!array_key_exists($source, $keyMap)) {
                $newResultList[] = $runData;
            } else {
                $this->deleteByRunData($runData, $xhprofManager);
            }

            $keyMap[$source] = $source;
        }

        return $newResultList;
    }

    private function deleteByRunData(array $runData, XHProfMangerInterface $xhprofManager): void
    {
        $xhprofManager->deleteById($runData['run'], base64_decode($runData['source']));
    }

    private function getSortedResultListByKey(string $key, ?XHProfMangerInterface $xhprofManager = null): array
    {
        $xhprofManager = $xhprofManager ?? static::getInstance()->getXHProfManager();
        $resultList = $this->getResultList($xhprofManager);
        usort($resultList, function ($a, $b) use ($key): int {
            $valueA = $a[$key] ?: 0;
            $valueB = $b[$key] ?: 0;
            if ($valueA == $valueB) {
                return 0;
            }
            return ($valueA < $valueB) ? 1 : -1;
        });
        return $resultList;
    }

    private function getResultList(?XHProfMangerInterface $xhprofManager = null): array
    {
        $resultList = [];
        $xhprofManager = $xhprofManager ?? static::getInstance()->getXHProfManager();
        foreach ($xhprofManager->getRunsList() as $run) {
            $decodedSource = base64_decode($run['source']);
            $data = $xhprofManager->getRunData($run['run'] ?: '', $decodedSource);
            $totalData = DataListHelper::getMaxValues($data);
            $resultList[] = [
                'run' => $run['run'] ?: '',
                'source' => $run['source'] ?: '',
                'date' => $run['date'] ?: null,
                'ct' => $totalData['ct'] ?: 0,
                'wt' => $totalData['wt'] ?: 0,
                'mu' => $totalData['mu'] ?: 0,
            ];
        }
        return $resultList;
    }

    private function getXHProfManager(): XHProfManager
    {
        if ($this->xhprofManager === null) {
            $this->xhprofManager = XHProfManager::instance();
            $this->xhprofManager->setStrategy(new DefaultChecker());
        }
        return $this->xhprofManager;
    }
}
