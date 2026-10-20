<?php

/**
 * Local Google Shopping checks and UTM assembly.
 * No network. Messages and codes match tools/wea-323/lib/feed.js and utm.js.
 */
final class WeaduFeedAudit
{
    const DEFAULT_MAX = 500;

    public static function messages()
    {
        return array(
            'missing_id' => 'id is empty.',
            'missing_title' => 'title is empty.',
            'title_too_long' => 'title is longer than 150 characters.',
            'title_too_short' => 'title is under 15 characters.',
            'title_promotional' => 'title contains promotional wording Google often rejects.',
            'title_all_caps' => 'title is all capitals.',
            'missing_description' => 'description is empty.',
            'description_too_long' => 'description is longer than 5000 characters.',
            'missing_link' => 'link is empty.',
            'link_not_http' => 'link must start with http:// or https://.',
            'missing_image' => 'image_link is empty.',
            'image_not_http' => 'image_link must start with http:// or https://.',
            'missing_availability' => 'availability is empty.',
            'availability_invalid' => 'availability must be in_stock, out_of_stock, preorder, or backorder.',
            'missing_price' => 'price is empty.',
            'price_unparsed' => 'price must look like 19.00 EUR.',
            'price_not_positive' => 'price must be greater than zero.',
            'price_currency_missing' => 'price has no ISO currency code.',
            'missing_brand' => 'brand is empty.',
            'missing_gtin' => 'gtin and mpn are both empty.',
            'gtin_invalid' => 'gtin check digit or length is invalid.',
            'missing_condition' => 'condition is empty.',
            'condition_invalid' => 'condition must be new, refurbished, or used.',
            'duplicate_id' => 'id is duplicated in this feed.',
            'empty_feed' => 'No product was found in this text.',
            'truncated' => 'Only the first 500 products were checked.',
            'url_required' => 'Landing URL is required.',
            'url_invalid' => 'Landing URL is not a valid URL.',
            'url_protocol' => 'Landing URL must start with http:// or https://.',
            'utm_source_required' => 'utm_source is required.',
            'utm_medium_required' => 'utm_medium is required.',
            'utm_campaign_required' => 'utm_campaign is required.',
            'utm_value_invalid' => 'Use letters, digits, and . _ ~ - with no spaces (80 characters max).',
        );
    }

    public static function label($code, $iso)
{
    $code = (string) $code;
    if (strpos(strtolower((string) $iso), 'fr') === 0) {
        $fr = array(
            'missing_id' => 'Identifiant vide.',
            'missing_title' => 'Titre vide.',
            'title_too_long' => 'Titre de plus de 150 caractères.',
            'title_too_short' => 'Titre de moins de 15 caractères.',
            'title_promotional' => 'Le titre contient une formule promotionnelle souvent refusée.',
            'title_all_caps' => 'Titre tout en capitales.',
            'missing_description' => 'Description vide.',
            'description_too_long' => 'Description de plus de 5 000 caractères.',
            'missing_link' => 'Lien vide.',
            'link_not_http' => 'Le lien doit commencer par http:// ou https://.',
            'missing_image' => 'Image vide.',
            'image_not_http' => 'L\'image doit commencer par http:// ou https://.',
            'missing_availability' => 'Disponibilité vide.',
            'availability_invalid' => 'Disponibilité hors liste (in_stock, out_of_stock, preorder, backorder).',
            'missing_price' => 'Prix vide.',
            'price_unparsed' => 'Prix illisible. Forme attendue : 19.00 EUR.',
            'price_not_positive' => 'Le prix doit être supérieur à zéro.',
            'price_currency_missing' => 'Prix sans code devise ISO.',
            'missing_brand' => 'Marque vide.',
            'missing_gtin' => 'GTIN et MPN vides.',
            'gtin_invalid' => 'GTIN : longueur ou clé de contrôle invalide.',
            'missing_condition' => 'État vide.',
            'condition_invalid' => 'État hors liste (new, refurbished, used).',
            'duplicate_id' => 'Identifiant en double.',
            'empty_feed' => 'Aucun produit.',
            'truncated' => 'Seuls les 500 premiers produits ont été lus.',
        );
        if (isset($fr[$code])) {
            return $fr[$code];
        }
    }
    $messages = self::messages();
    return isset($messages[$code]) ? $messages[$code] : $code;
}

public static function gtinIsValid($value)
    {
        $digits = preg_replace('/[\s-]/', '', (string) $value);
        if (!preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $digits)) {
            return false;
        }
        $sum = 0;
        $weight = 3;
        for ($i = strlen($digits) - 2; $i >= 0; $i--) {
            $sum += (int) $digits[$i] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }
        $expected = (10 - ($sum % 10)) % 10;
        return $expected === (int) $digits[strlen($digits) - 1];
    }

    public static function suggestTitle(array $item)
    {
        $title = trim((string) (isset($item['title']) ? $item['title'] : ''));
        $brand = trim((string) (isset($item['brand']) ? $item['brand'] : ''));
        $next = $title;
        if ($next === '' && $brand !== '') {
            $next = $brand;
        } elseif ($brand !== '' && $next !== '' && stripos($next, $brand) === false) {
            $next = $brand . ' ' . $next;
        }
        if (self::charLength($next) > 150) {
            $next = rtrim(self::cut($next, 150));
        }
        if ($next === $title) {
            return '';
        }
        return $next;
    }

    public static function auditItems(array $items, $maxItems = null)
    {
        $cap = $maxItems === null ? self::DEFAULT_MAX : (int) $maxItems;
        $truncated = count($items) > $cap;
        $slice = $truncated ? array_slice($items, 0, $cap) : $items;
        $seen = array();
        $reports = array();
        foreach ($slice as $item) {
            $report = self::auditItem(is_array($item) ? $item : array());
            if ($report['id'] !== '') {
                if (isset($seen[$report['id']])) {
                    $report['findings'][] = self::finding('duplicate_id', 'error', 'id');
                } else {
                    $seen[$report['id']] = true;
                }
            }
            $reports[] = $report;
        }
        $errors = array();
        if (!count($items)) {
            $errors[] = self::finding('empty_feed', 'error', 'feed');
        }
        if ($truncated) {
            $errors[] = self::finding('truncated', 'warning', 'feed');
        }
        $ok = true;
        foreach ($errors as $error) {
            if ($error['severity'] === 'error') {
                $ok = false;
            }
        }
        foreach ($reports as $report) {
            foreach ($report['findings'] as $row) {
                if ($row['severity'] === 'error') {
                    $ok = false;
                }
            }
        }
        return array(
            'ok' => $ok,
            'format' => 'items',
            'truncated' => $truncated,
            'errors' => $errors,
            'items' => $reports,
            'counts' => self::countReport($reports),
        );
    }

    public static function buildUtmUrl(array $input)
    {
        $errors = array();
        $rawUrl = trim((string) (isset($input['url']) ? $input['url'] : ''));
        if ($rawUrl === '') {
            $errors[] = self::finding('url_required', 'error', 'url');
        }
        $values = array(
            'source' => self::normalizeUtm(isset($input['source']) ? $input['source'] : '', 'source', true, $errors),
            'medium' => self::normalizeUtm(isset($input['medium']) ? $input['medium'] : '', 'medium', true, $errors),
            'campaign' => self::normalizeUtm(isset($input['campaign']) ? $input['campaign'] : '', 'campaign', true, $errors),
            'content' => self::normalizeUtm(isset($input['content']) ? $input['content'] : '', 'content', false, $errors),
            'term' => self::normalizeUtm(isset($input['term']) ? $input['term'] : '', 'term', false, $errors),
        );

        $parts = $rawUrl === '' ? false : parse_url($rawUrl);
        if ($rawUrl !== '') {
            if ($parts === false || empty($parts['scheme'])) {
                $errors[] = self::finding('url_invalid', 'error', 'url');
                $parts = false;
            } elseif ($parts['scheme'] !== 'http' && $parts['scheme'] !== 'https') {
                $errors[] = self::finding('url_protocol', 'error', 'url');
                $parts = false;
            } elseif (empty($parts['host'])) {
                $errors[] = self::finding('url_invalid', 'error', 'url');
                $parts = false;
            }
        }

        if ($errors || !$parts) {
            return array('ok' => false, 'url' => '', 'errors' => $errors);
        }

        $pairs = array();
        if (!empty($parts['query'])) {
            foreach (explode('&', $parts['query']) as $part) {
                if ($part === '') {
                    continue;
                }
                $eq = strpos($part, '=');
                $key = rawurldecode($eq === false ? $part : substr($part, 0, $eq));
                $value = $eq === false ? '' : rawurldecode(substr($part, $eq + 1));
                if (strpos($key, 'utm_') === 0) {
                    continue;
                }
                $pairs[] = array($key, $value);
            }
        }
        $utm = array(
            'utm_source' => $values['source'],
            'utm_medium' => $values['medium'],
            'utm_campaign' => $values['campaign'],
            'utm_content' => $values['content'],
            'utm_term' => $values['term'],
        );
        foreach ($utm as $key => $value) {
            if ($value !== '') {
                $pairs[] = array($key, $value);
            }
        }

        $query = array();
        foreach ($pairs as $pair) {
            $query[] = rawurlencode($pair[0]) . '=' . rawurlencode($pair[1]);
        }
        $url = $parts['scheme'] . '://';
        if (!empty($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }
        $url .= strtolower($parts['host']);
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        $url .= isset($parts['path']) ? $parts['path'] : '/';
        if ($query) {
            $url .= '?' . implode('&', $query);
        }
        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        return array('ok' => true, 'url' => $url, 'errors' => array());
    }

    public static function toCsv(array $report)
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array('id', 'title', 'severity', 'code', 'field', 'message', 'suggested_title'));
        foreach ($report['items'] as $item) {
            if (!$item['findings']) {
                fputcsv($handle, array($item['id'], $item['title'], '', '', '', '', $item['suggestedTitle']));
                continue;
            }
            foreach ($item['findings'] as $row) {
                fputcsv($handle, array(
                    $item['id'],
                    $item['title'],
                    $row['severity'],
                    $row['code'],
                    $row['field'],
                    $row['message'],
                    $item['suggestedTitle'],
                ));
            }
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }

    private static function auditItem(array $item)
    {
        $id = trim((string) (isset($item['id']) ? $item['id'] : ''));
        $title = trim((string) (isset($item['title']) ? $item['title'] : ''));
        $description = self::plain(isset($item['description']) ? $item['description'] : '');
        $link = trim((string) (isset($item['link']) ? $item['link'] : ''));
        $image = trim((string) (isset($item['image_link']) ? $item['image_link'] : ''));
        $availability = strtolower(trim((string) (isset($item['availability']) ? $item['availability'] : '')));
        $availability = preg_replace('/\s+/', '_', $availability);
        $brand = trim((string) (isset($item['brand']) ? $item['brand'] : ''));
        $gtin = preg_replace('/[\s-]/', '', (string) (isset($item['gtin']) ? $item['gtin'] : ''));
        $mpn = trim((string) (isset($item['mpn']) ? $item['mpn'] : ''));
        $condition = strtolower(trim((string) (isset($item['condition']) ? $item['condition'] : '')));
        $findings = array();

        if ($id === '') {
            $findings[] = self::finding('missing_id', 'error', 'id');
        }
        if ($title === '') {
            $findings[] = self::finding('missing_title', 'error', 'title');
        } else {
            $length = self::charLength($title);
            if ($length > 150) {
                $findings[] = self::finding('title_too_long', 'error', 'title');
            } elseif ($length < 15) {
                $findings[] = self::finding('title_too_short', 'warning', 'title');
            }
            if (preg_match('/\b(free shipping|livraison gratuite|envio gratis|envío gratis|sale|soldes?|%\s?off|discount|réduction|promo)\b/iu', $title)) {
                $findings[] = self::finding('title_promotional', 'warning', 'title');
            }
            if (self::isAllCaps($title)) {
                $findings[] = self::finding('title_all_caps', 'warning', 'title');
            }
        }
        if ($description === '') {
            $findings[] = self::finding('missing_description', 'error', 'description');
        } elseif (self::charLength($description) > 5000) {
            $findings[] = self::finding('description_too_long', 'error', 'description');
        }
        if ($link === '') {
            $findings[] = self::finding('missing_link', 'error', 'link');
        } elseif (!preg_match('#^https?://#i', $link)) {
            $findings[] = self::finding('link_not_http', 'error', 'link');
        }
        if ($image === '') {
            $findings[] = self::finding('missing_image', 'error', 'image_link');
        } elseif (!preg_match('#^https?://#i', $image)) {
            $findings[] = self::finding('image_not_http', 'error', 'image_link');
        }
        if ($availability === '') {
            $findings[] = self::finding('missing_availability', 'error', 'availability');
        } elseif (!in_array($availability, array('in_stock', 'out_of_stock', 'preorder', 'backorder'), true)) {
            $findings[] = self::finding('availability_invalid', 'error', 'availability');
        }
        foreach (self::priceFindings(isset($item['price']) ? $item['price'] : '') as $priceFinding) {
            $findings[] = $priceFinding;
        }
        if ($brand === '') {
            $findings[] = self::finding('missing_brand', 'warning', 'brand');
        }
        $waived = self::identifierWaived(isset($item['identifier_exists']) ? $item['identifier_exists'] : '');
        if ($gtin === '' && $mpn === '' && !$waived) {
            $findings[] = self::finding('missing_gtin', 'warning', 'gtin');
        } elseif ($gtin !== '' && !self::gtinIsValid($gtin)) {
            $findings[] = self::finding('gtin_invalid', 'error', 'gtin');
        }
        if ($condition === '') {
            $findings[] = self::finding('missing_condition', 'warning', 'condition');
        } elseif (!in_array($condition, array('new', 'refurbished', 'used'), true)) {
            $findings[] = self::finding('condition_invalid', 'warning', 'condition');
        }

        return array(
            'id' => $id,
            'title' => $title,
            'suggestedTitle' => self::suggestTitle($item),
            'findings' => $findings,
        );
    }

    private static function priceFindings($price)
    {
        $raw = trim((string) $price);
        if ($raw === '') {
            return array(self::finding('missing_price', 'error', 'price'));
        }
        if (!preg_match('/^(?:([A-Za-z]{3})\s*)?(\d+(?:[.,]\d+)?)(?:\s*([A-Za-z]{3}))?$/', $raw, $match)) {
            return array(self::finding('price_unparsed', 'error', 'price'));
        }
        $num = (float) str_replace(',', '.', $match[2]);
        $currency = $match[1] !== '' ? $match[1] : (isset($match[3]) ? $match[3] : '');
        $found = array();
        if (!($num > 0)) {
            $found[] = self::finding('price_not_positive', 'error', 'price');
        }
        if ($currency === '') {
            $found[] = self::finding('price_currency_missing', 'warning', 'price');
        }
        return $found;
    }

    private static function normalizeUtm($raw, $field, $required, array &$errors)
    {
        $value = trim((string) $raw);
        if ($value === '') {
            if ($required) {
                $errors[] = self::finding('utm_' . $field . '_required', 'error', $field);
            }
            return '';
        }
        if (strlen($value) > 80 || !preg_match('/^[A-Za-z0-9._~-]+$/', $value)) {
            $errors[] = self::finding('utm_value_invalid', 'error', $field);
            return '';
        }
        return strtolower($value);
    }

    private static function identifierWaived($value)
    {
        $flag = strtolower(trim((string) $value));
        return $flag === 'no' || $flag === 'false' || $flag === '0';
    }

    private static function isAllCaps($title)
    {
        if (!preg_match_all('/\p{L}/u', $title, $letters)) {
            return false;
        }
        if (count($letters[0]) < 8) {
            return false;
        }
        foreach ($letters[0] as $ch) {
            $upper = function_exists('mb_strtoupper') ? mb_strtoupper($ch, 'UTF-8') : strtoupper($ch);
            $lower = function_exists('mb_strtolower') ? mb_strtolower($ch, 'UTF-8') : strtolower($ch);
            if ($ch !== $upper || $ch === $lower) {
                return false;
            }
        }
        return true;
    }

    private static function plain($value)
    {
        $text = preg_replace('/<[^>]*>/', ' ', (string) $value);
        $text = preg_replace('/&nbsp;/i', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private static function charLength($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen((string) $value, 'UTF-8');
        }
        return strlen((string) $value);
    }

    private static function cut($value, $length)
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length, 'UTF-8');
        }
        return substr($value, 0, $length);
    }

    private static function finding($code, $severity, $field)
    {
        $messages = self::messages();
        return array(
            'code' => $code,
            'severity' => $severity,
            'field' => $field,
            'message' => isset($messages[$code]) ? $messages[$code] : $code,
        );
    }

    private static function countReport(array $items)
    {
        $errors = 0;
        $warnings = 0;
        foreach ($items as $item) {
            foreach ($item['findings'] as $row) {
                if ($row['severity'] === 'error') {
                    $errors++;
                } else {
                    $warnings++;
                }
            }
        }
        return array('items' => count($items), 'errors' => $errors, 'warnings' => $warnings);
    }
}
