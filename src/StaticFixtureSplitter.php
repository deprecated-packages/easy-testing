<?php

declare(strict_types=1);

namespace Symplify\EasyTesting;

use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use Symplify\EasyTesting\ValueObject\SplitLine;
use Symplify\SmartFileSystem\SmartFileInfo;

final class StaticFixtureSplitter
{
    /**
     * @var string|null
     */
    public static $customTemporaryPath;

    /**
     * @return string[]
     */
    public static function splitFileInfoToInputAndExpected(SmartFileInfo $smartFileInfo): array
    {
        if (Strings::match($smartFileInfo->getContents(), SplitLine::SPLIT_LINE)) {
            // original → expected
            [$original, $expected] = Strings::split($smartFileInfo->getContents(), SplitLine::SPLIT_LINE);

            $expected = self::retypeExpected($expected);

            return [$original, $expected];
        }

        // no changes
        return [$smartFileInfo->getContents(), $smartFileInfo->getContents()];
    }

    /**
     * @return SmartFileInfo[]
     */
    public static function splitFileInfoToLocalInputAndExpectedFileInfos(
        SmartFileInfo $smartFileInfo,
        bool $autoloadTestFixture = false
    ): array {
        [$originalContent, $expectedContent] = self::splitFileInfoToInputAndExpected($smartFileInfo);

        $originalFileInfo = self::createTemporaryFileInfo($smartFileInfo, 'original', $originalContent);
        $expectedFileInfo = self::createTemporaryFileInfo($smartFileInfo, 'expected', $expectedContent);

        // some files needs to be autoload to enable reflection
        if ($autoloadTestFixture) {
            require_once $originalFileInfo->getRealPath();
        }

        return [$originalFileInfo, $expectedFileInfo];
    }

    /**
     * @return SmartFileInfo[]|mixed[]
     */
    public static function splitFileInfoToInputFileInfoAndExpected(SmartFileInfo $smartFileInfo): array
    {
        [$originalContent, $expectedContent] = self::splitFileInfoToInputAndExpected($smartFileInfo);

        $originalFileInfo = self::createTemporaryFileInfo($smartFileInfo, 'original', $originalContent);
        return [$originalFileInfo, $expectedContent];
    }

    public static function getTemporaryPath(): string
    {
        if (self::$customTemporaryPath !== null) {
            return self::$customTemporaryPath;
        }

        return sys_get_temp_dir() . '/_temp_fixture_easy_testing';
    }

    private static function createTemporaryFileInfo(
        SmartFileInfo $smartFileInfo,
        string $prefix,
        string $fileContent
    ): SmartFileInfo {
        $temporaryFilePath = self::createTemporaryPathWithPrefix($smartFileInfo, $prefix);
        FileSystem::write($temporaryFilePath, $fileContent);

        return new SmartFileInfo($temporaryFilePath);
    }

    private static function createTemporaryPathWithPrefix(SmartFileInfo $smartFileInfo, string $prefix): string
    {
        $hash = Strings::substring(md5($smartFileInfo->getRealPath()), -20);

        $fileBaseName = $smartFileInfo->getBasename('.inc');

        return self::getTemporaryPath() . sprintf('/%s_%s_%s', $prefix, $hash, $fileBaseName);
    }

    private static function retypeExpected($expected)
    {
        if (! is_numeric(trim($expected))) {
            return $expected;
        }

        // value re-type
        if (strlen((string) (int) $expected) === strlen(trim($expected))) {
            return (int) $expected;
        }
        if (strlen((string) (float) $expected) === strlen(trim($expected))) {
            return (float) $expected;
        }

        return $expected;
    }
}
