<?php

namespace Ernestdefoe\Dossier;

/**
 * Links members type: allow-listed by scheme, with the brand icon of the site.
 *
 * 🚨 Control characters are stripped BEFORE the scheme is checked. Browsers
 * drop them while parsing, so "\x01javascript:alert(1)" is a script link that
 * a scheme check on the raw string waves through.
 */
final class Url
{
    /** The kinds of link a member can add, and the icon each one gets. */
    public const KINDS = [
        'website' => 'fas fa-globe',
        'linkedin' => 'fab fa-linkedin',
        'github' => 'fab fa-github',
        'gitlab' => 'fab fa-gitlab',
        'x' => 'fab fa-x-twitter',
        'mastodon' => 'fab fa-mastodon',
        'custom' => 'fas fa-link',
    ];

    /** Sites whose links get their brand icon when added as a website or custom link. */
    private const BRANDS = [
        'github.com' => ['fab fa-github', 'GitHub'],
        'gitlab.com' => ['fab fa-gitlab', 'GitLab'],
        'linkedin.com' => ['fab fa-linkedin', 'LinkedIn'],
        'x.com' => ['fab fa-x-twitter', 'X'],
        'twitter.com' => ['fab fa-x-twitter', 'X'],
        'mastodon.social' => ['fab fa-mastodon', 'Mastodon'],
        'bitbucket.org' => ['fab fa-bitbucket', 'Bitbucket'],
        'stackoverflow.com' => ['fab fa-stack-overflow', 'Stack Overflow'],
        'dev.to' => ['fab fa-dev', 'DEV'],
        'medium.com' => ['fab fa-medium', 'Medium'],
        'youtube.com' => ['fab fa-youtube', 'YouTube'],
        'youtu.be' => ['fab fa-youtube', 'YouTube'],
        'bsky.app' => ['fab fa-bluesky', 'Bluesky'],
        'instagram.com' => ['fab fa-instagram', 'Instagram'],
        'facebook.com' => ['fab fa-facebook', 'Facebook'],
        'dribbble.com' => ['fab fa-dribbble', 'Dribbble'],
        'behance.net' => ['fab fa-behance', 'Behance'],
        'npmjs.com' => ['fab fa-npm', 'npm'],
        'codepen.io' => ['fab fa-codepen', 'CodePen'],
        'discord.gg' => ['fab fa-discord', 'Discord'],
        'discord.com' => ['fab fa-discord', 'Discord'],
        'twitch.tv' => ['fab fa-twitch', 'Twitch'],
        'kaggle.com' => ['fab fa-kaggle', 'Kaggle'],
        'orcid.org' => ['fab fa-orcid', 'ORCID'],
        'researchgate.net' => ['fab fa-researchgate', 'ResearchGate'],
    ];

    /** An http(s) address, cleaned, or null when it is anything else. */
    public static function safe(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        // Every control and format character (C0, DEL, C1, zero-width, bidi overrides), then spaces at the ends.
        $url = trim((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $url));

        if ($url === '' || strlen($url) > 500 || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || preg_match('/[\s<>"\'`\\\\]/', $url)) {
            return null;
        }

        return $url;
    }

    /** An http(s) address, or a path on this forum (one slash, not followed by a second one or a backslash). */
    public static function safeOrPath(mixed $url): ?string
    {
        if (is_string($url) && preg_match('#^/(?![/\\\\])[^\s<>"\'`\\\\\x00-\x1f\x7f]*$#', $url) && strlen($url) <= 500) {
            return $url;
        }

        return self::safe($url);
    }

    public static function host(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return (string) preg_replace('/^(www|m|mobile)\./', '', $host);
    }

    /**
     * A link's icon and the name of its site.
     *
     * @return array{0: string, 1: string}
     */
    public static function icon(string $kind, string $url): array
    {
        $host = self::host($url);

        foreach (self::BRANDS as $domain => $brand) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return in_array($kind, ['website', 'custom'], true) ? $brand : [self::KINDS[$kind] ?? $brand[0], $brand[1]];
            }
        }

        return [self::KINDS[$kind] ?? self::KINDS['custom'], $host];
    }
}
