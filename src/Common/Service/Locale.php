<?php

namespace Nails\Common\Service;

use Nails\Factory;

/**
 * Class Locale
 *
 * @package Nails\Common\Service
 */
class Locale
{
    /**
     * The default locale's language segment
     *
     * @var string
     */
    const DEFAULT_LANGUAGE = 'en';

    /**
     * The default locale's region segment
     *
     * @var string
     */
    const DEFAULT_REGION = 'GB';

    /**
     * The default locale's script segment
     *
     * @var string
     */
    const DEFAULT_SCRIPT = '';

    /**
     * The name of the query parameter to look for a locale in
     *
     * @var string
     */
    const QUERY_PARAM = 'locale';

    /**
     * The name of the cookie to store a locale in
     *
     * @var string
     */
    const COOKIE_NAME = 'locale';

    /**
     * The supported locales by the system (in addition to the default locale)
     *
     * @var string[]
     */
    const SUPPORTED_LOCALES = [];

    /**
     * Maps vanity URL strings to supported locales
     *
     * @var string[]
     */
    const URL_VANITY_MAP = [];

    /**
     * Enable locale detection via the request header
     *
     * @var bool
     */
    const ENABLE_SNIFF_HEADER = true;

    /**
     * Enable locale detection via the active user
     *
     * @var bool
     */
    const ENABLE_SNIFF_USER = true;

    /**
     * Enable locale detection via the URL (i.e. /{language}
     *
     * @var bool
     */
    const ENABLE_SNIFF_URL = true;

    /**
     * Enable locale detection via a cookie
     *
     * @var bool
     */
    const ENABLE_SNIFF_COOKIE = true;

    /**
     * Enable locale detection via a query parameter
     *
     * @var bool
     */
    const ENABLE_SNIFF_QUERY = true;

    /**
     * In models which use the Localised trait, if an exact match cannot be
     * found, fallback to the default locale rather than attempt to find a
     * similar item with the same language, but a different region
     *
     * @var bool
     */
    const MODEL_FALLBACK_TO_DEFAULT_LOCALE = false;

    // --------------------------------------------------------------------------

    /**
     * The active Locale
     *
     * @var \Nails\Common\Factory\Locale
     */
    protected $oLocale;

    /**
     * The Input service
     *
     * @var Input
     */
    protected $oInput;

    /**
     * The supported locales
     *
     * @var \Nails\Common\Factory\Locale[]
     */
    protected $aSupportedLocales = [];

    // --------------------------------------------------------------------------

    /**
     * Locale constructor.
     *
     * @param Input                             $oInput  The input service
     * @param \Nails\Common\Factory\Locale|null $oLocale The locale to use, automatically detected
     */
    public function __construct(
        Input $oInput,
        \Nails\Common\Factory\Locale $oLocale = null
    ) {
        $this->oInput = $oInput;

        $this->aSupportedLocales[] = Factory::factory('Locale')
            ->setLanguage(Factory::factory('LocaleLanguage', null, static::DEFAULT_LANGUAGE))
            ->setRegion(Factory::factory('LocaleRegion', null, static::DEFAULT_REGION))
            ->setScript(Factory::factory('LocaleScript', null, static::DEFAULT_SCRIPT));

        foreach (static::SUPPORTED_LOCALES as $sLocale) {
            list($sLanguage, $sRegion, $sScript) = static::parseLocaleString($sLocale);
            $this->aSupportedLocales[] = Factory::factory('Locale')
                ->setLanguage(Factory::factory('LocaleLanguage', null, $sLanguage))
                ->setRegion(Factory::factory('LocaleRegion', null, $sRegion))
                ->setScript(Factory::factory('LocaleScript', null, $sScript));
        }

        $this->oLocale = $oLocale ?? $this->detect();
    }

    // --------------------------------------------------------------------------

    /**
     * Attempts to detect the locale from the request
     *
     * @return \Nails\Common\Factory\Locale|null
     */
    public function detect(): \Nails\Common\Factory\Locale
    {
        /**
         * Detect the locale from the request, weakest first
         * - Request headers
         * - Active user preference
         * - The URL (/{locale}/.*)
         * - A locale cookie
         * - Explicitly provided via $_GET['locale']
         */

        $oLocale = $this->getDefautLocale();
        $this
            ->sniffHeader($oLocale)
            ->sniffActiveUser($oLocale)
            ->sniffUrl($oLocale)
            ->sniffCookie($oLocale)
            ->sniffQuery($oLocale);

        return $this
            ->set($oLocale)
            ->get();
    }

    // --------------------------------------------------------------------------

    /**
     * Parses the request headers for a locale and updates $oLocale object
     *
     * @param \Nails\Common\Factory\Locale $oLocale The locale object to update
     *
     * @return $this
     */
    protected function sniffHeader(\Nails\Common\Factory\Locale &$oLocale)
    {
        if (static::ENABLE_SNIFF_HEADER) {
            $this->setFromString(
                $oLocale,
                $this->oInput->server('HTTP_ACCEPT_LANGUAGE') ?? ''
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Checks the user locale preference and updates $oLocale object
     *
     * @param \Nails\Common\Factory\Locale $oLocale The locale object to update
     *
     * @return $this
     */
    protected function sniffActiveUser(\Nails\Common\Factory\Locale &$oLocale)
    {
        if (static::ENABLE_SNIFF_USER) {
            $this->setFromString(
                $oLocale,
                activeUser('locale') ?? ''
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Parses the URL for a langauge and updates $oLocale object
     *
     * @param \Nails\Common\Factory\Locale $oLocale The locale object to update
     *
     * @return $this
     */
    protected function sniffUrl(\Nails\Common\Factory\Locale &$oLocale)
    {
        if (static::ENABLE_SNIFF_URL) {

            //  Manually query the URL as CI might not be available
            $sUrl = preg_replace(
                '/\/index\.php\/(.*)/',
                '$1',
                $this->oInput->server('PHP_SELF')
            );

            preg_match($this->getUrlRegex(), $sUrl, $aMatches);
            $sLocale = !empty($aMatches[1]) ? $aMatches[1] : '';

            //  If it's a vanity locale, then convert to the full locale
            if (array_search($sLocale, static::URL_VANITY_MAP) !== false) {
                $sLocale = array_search($sLocale, static::URL_VANITY_MAP);
            }

            $this->setFromString($oLocale, $sLocale);
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Looks for a locale cookie and updates the $oLocale object
     *
     * @param \Nails\Common\Factory\Locale $oLocale The locale object to update
     *
     * @return $this
     */
    protected function sniffCookie(\Nails\Common\Factory\Locale &$oLocale)
    {
        if (static::ENABLE_SNIFF_COOKIE) {
            $this->setFromString(
                $oLocale,
                $this->oInput->get(static::COOKIE_NAME) ?? null
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Looks for a locale in the query string and updates the locale object
     *
     * @param \Nails\Common\Factory\Locale $oLocale The locale object to update
     *
     * @return $this
     */
    protected function sniffQuery(\Nails\Common\Factory\Locale &$oLocale)
    {
        if (static::ENABLE_SNIFF_QUERY) {
            $this->setFromString(
                $oLocale,
                $this->oInput->get(static::QUERY_PARAM) ?? null
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Updates $oLocale based on values parsed from $sLocale
     *
     * @param \Nails\Common\Factory\Locale $oLocale The Locale object to update
     * @param string                       $sLocale The string to parse
     *
     * @return \Nails\Common\Factory\Locale
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function setFromString(\Nails\Common\Factory\Locale &$oLocale, string $sLocale): ?\Nails\Common\Factory\Locale
    {
        if (!empty($sLocale)) {

            list($sLanguage, $sRegion, $sScript) = static::parseLocaleString($sLocale);

            if ($sLanguage) {
                $oLocale
                    ->setLanguage(Factory::factory('LocaleLanguage', null, $sLanguage));
            }

            if ($sRegion) {
                $oLocale
                    ->setRegion(Factory::factory('LocaleRegion', null, $sRegion));
            }

            if ($sScript) {
                $oLocale
                    ->setScript(Factory::factory('LocaleScript', null, $sScript));
            }
        }

        return $oLocale;
    }

    // --------------------------------------------------------------------------

    /**
     * Parses a locale string into it's respective framgments
     *
     * @param string $sLocale The string to parse
     *
     * @return string[]
     */
    public static function parseLocaleString(?string $sLocale): array
    {
        return [
            \Locale::getPrimaryLanguage($sLocale),
            \Locale::getRegion($sLocale),
            \Locale::getScript($sLocale),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Manually sets a locale
     *
     * @param \Nails\Common\Factory\Locale $oLocale
     */
    public function set(\Nails\Common\Factory\Locale $oLocale = null): self
    {
        $this->oLocale = $oLocale;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the active Locale
     *
     * @return \Nails\Common\Factory\Locale
     */
    public function get(): \Nails\Common\Factory\Locale
    {
        return $this->oLocale;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the default locale to use for the system
     *
     * @return \Nails\Common\Factory\Locale
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function getDefautLocale(): \Nails\Common\Factory\Locale
    {
        return Factory::factory('Locale')
            ->setLanguage(Factory::factory('LocaleLanguage', null, static::DEFAULT_LANGUAGE))
            ->setRegion(Factory::factory('LocaleRegion', null, static::DEFAULT_REGION))
            ->setScript(Factory::factory('LocaleScript', null, static::DEFAULT_SCRIPT));
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the supported locales for this system
     *
     * @return \Nails\Common\Factory\Locale[]
     */
    public function getSupportedLocales(): array
    {
        return $this->aSupportedLocales;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a regex suitable for detecting a language flag at the beginning of the URL
     *
     * @return string
     */
    public function getUrlRegex(): string
    {
        $aSupportedLocales = $this->getSupportedLocales();
        $aUrlLocales       = [];

        foreach ($aSupportedLocales as $oLocale) {
            $sVanity       = $this->getUrlSegment($oLocale);
            $aUrlLocales[] = $sVanity;
        }

        return '/^(' . implode('|', array_filter($aUrlLocales)) . ')?(\/)?(.*)$/';
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an emoji flag for a locale
     *
     * @param \Nails\Common\Factory\Locale $oLocale The locale to query
     *
     * @return string
     */
    public static function flagEmoji(\Nails\Common\Factory\Locale $oLocale): string
    {
        $sRegion    = $oLocale->getRegion();
        $aCountries = json_decode(
            file_get_contents(
                NAILS_APP_PATH . 'vendor/annexare/countries-list/dist/countries.emoji.json'
            )
        );

        return !empty($aCountries->{$sRegion}->emoji) ? $aCountries->{$sRegion}->emoji : '';
    }


    // --------------------------------------------------------------------------

    /**
     * Returns the URL prefix for a given locale, considering any vanity preferences
     *
     * @param \Nails\Common\Factory\Locale $oLocale The locale to query
     *
     * @return string
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function getUrlSegment(\Nails\Common\Factory\Locale $oLocale): string
    {
        if ($oLocale == $this->getDefautLocale()) {
            return '';
        } else {
            return getFromArray((string) $oLocale, static::URL_VANITY_MAP, (string) $oLocale);
        }
    }
}
