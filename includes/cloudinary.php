<?php
/**
 * Cloudinary Integration for ChandraKala Jewellers
 * Handles serverless uploads and optimized CDN URL generation without Composer.
 */

require_once __DIR__ . '/../config/env.php';

if (!function_exists('cj_cloudinary_config')) {
    function cj_cloudinary_config(): array
    {
        return [
            'cloud_name' => cj_env('CLOUDINARY_CLOUD_NAME', 'hihrgcxm'),
            'api_key'    => cj_env('CLOUDINARY_API_KEY', '695347713464272'),
            'api_secret' => cj_env('CLOUDINARY_API_SECRET', 'GeN1_27S_Jc-YZk0STvM_npGMaQ'),
        ];
    }
}

if (!function_exists('cj_cloudinary_is_enabled')) {
    function cj_cloudinary_is_enabled(): bool
    {
        $cfg = cj_cloudinary_config();
        return !empty($cfg['cloud_name']) && !empty($cfg['api_key']) && !empty($cfg['api_secret']);
    }
}

if (!function_exists('cj_cloudinary_upload')) {
    /**
     * Upload a local or temporary file directly to Cloudinary.
     *
     * @param string $localFilePath Path to the file (e.g. $_FILES['image']['tmp_name'])
     * @param string|null $publicId Desired public ID / filename (without extension)
     * @param string $folder Folder in Cloudinary (e.g. 'chandrakala/uploads' or 'chandrakala/catalogue')
     * @return array{success:bool, secure_url?:string, public_id?:string, error?:string}
     */
    function cj_cloudinary_upload(string $localFilePath, ?string $publicId = null, string $folder = 'chandrakala/uploads'): array
    {
        if (!file_exists($localFilePath) || !is_readable($localFilePath)) {
            return ['success' => false, 'error' => 'Source file does not exist or is not readable: ' . $localFilePath];
        }

        $cfg = cj_cloudinary_config();
        if (empty($cfg['cloud_name']) || empty($cfg['api_key']) || empty($cfg['api_secret'])) {
            return ['success' => false, 'error' => 'Cloudinary credentials are not configured.'];
        }

        $timestamp = time();
        $params = [
            'folder'    => $folder,
            'timestamp' => $timestamp,
        ];

        if (!empty($publicId)) {
            // Strip any extension from public_id to let Cloudinary manage format
            $cleanId = pathinfo($publicId, PATHINFO_FILENAME);
            $params['public_id'] = $cleanId;
        }

        // Generate SHA1 signature
        ksort($params);
        $toSign = '';
        foreach ($params as $k => $v) {
            $toSign .= $k . '=' . $v . '&';
        }
        $toSign = rtrim($toSign, '&') . $cfg['api_secret'];
        $signature = sha1($toSign);

        $postData = [
            'file'      => new CURLFile($localFilePath),
            'api_key'   => $cfg['api_key'],
            'timestamp' => (string) $timestamp,
            'folder'    => $folder,
            'signature' => $signature,
        ];

        if (!empty($params['public_id'])) {
            $postData['public_id'] = $params['public_id'];
        }

        $url = 'https://api.cloudinary.com/v1_1/' . rawurlencode($cfg['cloud_name']) . '/image/upload';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            return ['success' => false, 'error' => 'CURL error: ' . $curlError];
        }

        $data = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($data['secure_url'])) {
            return [
                'success'    => true,
                'secure_url' => $data['secure_url'],
                'public_id'  => $data['public_id'] ?? '',
                'format'     => $data['format'] ?? '',
                'width'      => $data['width'] ?? 0,
                'height'     => $data['height'] ?? 0,
            ];
        }

        $msg = $data['error']['message'] ?? ('HTTP ' . $httpCode . ' response from Cloudinary.');
        return ['success' => false, 'error' => $msg];
    }
}

if (!function_exists('cj_cloudinary_url')) {
    /**
     * Build an optimized Cloudinary delivery URL with automatic format and quality.
     *
     * @param string $filename File name or public ID stored in database
     * @param int $width Desired width in pixels (e.g. 600)
     * @param string $folder 'uploads' or 'catalogue'
     * @return string
     */
    function cj_cloudinary_url(string $filename, int $width = 600, string $folder = 'uploads'): string
    {
        $filename = ltrim(trim($filename), '/');
        if ($filename === '') {
            return '';
        }

        // If already a full URL, handle directly
        if (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0) {
            if (strpos($filename, 'res.cloudinary.com') !== false && strpos($filename, '/upload/') !== false) {
                // Inject auto optimization into existing Cloudinary URL
                $transform = 'f_auto,q_auto,w_' . (int)$width . ',c_limit/';
                return str_replace('/upload/', '/upload/' . $transform, $filename);
            }
            return $filename;
        }

        $cfg = cj_cloudinary_config();
        $cloud = $cfg['cloud_name'] ?: 'hihrgcxm';

        $subFolder = ($folder === 'catalogue') ? 'catalogue' : 'uploads';
        $baseName = basename($filename);

        // Transformation: auto format (WebP/AVIF), auto compression, scale limit width
        $transform = 'f_auto,q_auto,w_' . (int)$width . ',c_limit';

        return "https://res.cloudinary.com/{$cloud}/image/upload/{$transform}/chandrakala/{$subFolder}/{$baseName}";
    }
}
