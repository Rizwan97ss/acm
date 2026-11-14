<?php

namespace App\Services\Webhooks;

/**
 * SSRF protection for webhook targets: HTTPS only, no credentials in the URL, and every
 * address the host resolves to must be public (no loopback, private, link-local or
 * reserved ranges such as cloud metadata at 169.254.169.254).
 *
 * The addresses that were checked are also used for the connection itself (a curl
 * --resolve pin), so a DNS answer that changes after the check (rebinding) is ignored.
 */
class WebhookUrlGuard
{
    /**
     * Returns why the URL is not allowed, or null when it is safe to call.
     */
    public function problem(string $url): ?string
    {
        return $this->inspect($url)['problem'];
    }

    /**
     * @return array{problem: string|null, pin: string|null}
     */
    public function inspect(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host']) || strtolower($parts['scheme'] ?? '') !== 'https') {
            return ['problem' => __('Use an https:// URL.'), 'pin' => null];
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return ['problem' => __('Do not put credentials in the URL.'), 'pin' => null];
        }

        if (config('webhooks.allow_private_targets')) {
            return ['problem' => null, 'pin' => null];
        }

        $host = trim($parts['host'], '[]');
        $isLiteral = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $addresses = $isLiteral ? [$host] : $this->resolve($host);

        if ($addresses === []) {
            return ['problem' => __('The host name could not be resolved.'), 'pin' => null];
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return ['problem' => __('The URL points to a private or reserved network address.'), 'pin' => null];
            }
        }

        $pin = $isLiteral ? null : sprintf('%s:%d:%s', $host, $parts['port'] ?? 443, str_contains($addresses[0], ':') ? "[{$addresses[0]}]" : $addresses[0]);

        return ['problem' => null, 'pin' => $pin];
    }

    /**
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
    }
}
