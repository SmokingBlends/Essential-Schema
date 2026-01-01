<?php
// File: includes/class-returns-schema.php
defined('ABSPATH') || exit;

class ES_Returns_Schema {
    public function __construct() {
        add_action('wp_head', [$this, 'output_jsonld'], 99);
    }

    public function output_jsonld(): void {
        if (!is_page('return-policy')) { return; }

        $domestic_returns = get_option('es_domestic_returns', []);
        $org_opts = get_option('es_org', []);
        $address_country = $org_opts['address_country'] ?? 'US';

        $policy = [
            '@context' => 'https://schema.org',
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => $address_country,
        ];

        if (!empty($domestic_returns['policy_name'])) {
            $policy['name'] = $domestic_returns['policy_name'];
        }

        if (!empty($domestic_returns['return_policy_category'])) {
            $policy['returnPolicyCategory'] = 'https://schema.org/' . $domestic_returns['return_policy_category'];
        }

        if (!empty($domestic_returns['days']) && $domestic_returns['return_policy_category'] === 'MerchantReturnFiniteReturnWindow') {
            $policy['merchantReturnDays'] = (int) $domestic_returns['days'];
        }

        if (!empty($domestic_returns['fees'])) {
            $policy['returnFees'] = 'https://schema.org/' . $domestic_returns['fees'];
        }

        if (!empty($domestic_returns['refund_type'])) {
            $policy['refundType'] = 'https://schema.org/' . $domestic_returns['refund_type'];
        }

        if (!empty($domestic_returns['return_method'])) {
            $methods = [];
            foreach ($domestic_returns['return_method'] as $method) {
                $methods[] = 'https://schema.org/' . $method;
            }
            $policy['returnMethod'] = $methods;
        }

        if (!empty($domestic_returns['description'])) {
            $policy['description'] = $domestic_returns['description'];
        }

        echo "<script type='application/ld+json'>" . wp_json_encode($policy) . "</script>\n";
    }
}
new ES_Returns_Schema();