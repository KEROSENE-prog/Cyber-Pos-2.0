<?php
declare(strict_types=1);

/**
 * CyberPOS 2.0 - SimpleMailer
 * Cliente SMTP ligero en PHP puro usando sockets de red (stream_socket_client).
 * Permite el envío de reportes gerenciales con cuerpo HTML y adjunto binario (.pdf o .xlsx).
 */
class SimpleMailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private int $timeout = 10;

    public function __construct(array $config = [])
    {
        $this->host      = (string) ($config['host'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com');
        $this->port      = (int)    ($config['port'] ?? getenv('SMTP_PORT') ?: 587);
        $this->username  = (string) ($config['username'] ?? getenv('SMTP_USER') ?: '');
        $this->password  = (string) ($config['password'] ?? getenv('SMTP_PASS') ?: '');
        $this->fromEmail = (string) ($config['from_email'] ?? getenv('SMTP_FROM') ?: ($this->username !== '' ? $this->username : 'cyberpos@lanerosgamer.com'));
        $this->fromName  = (string) ($config['from_name'] ?? 'CyberPOS - Laneros Gamer');
    }

    private function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }

    private function sendCommand($socket, string $cmd, array $expectedCodes): string
    {
        fwrite($socket, $cmd . "\r\n");
        $res = $this->readResponse($socket);
        $code = (int) substr($res, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException("Error SMTP tras comando '{$cmd}': {$res}");
        }
        return $res;
    }

    /**
     * Envía un correo con cuerpo HTML y adjunto binario (.pdf o .xlsx).
     */
    public function send(
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?string $attachmentData = null,
        string $attachmentName = 'reporte.pdf'
    ): array {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Dirección de correo destinatario inválida: ' . $toEmail];
        }

        if ($this->host === '') {
            return ['success' => false, 'error' => 'Servidor SMTP no configurado. Verifica el host y credenciales.'];
        }

        try {
            $remote = ($this->port === 465 ? 'ssl://' : '') . $this->host . ':' . $this->port;
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
            if (!$socket) {
                return [
                    'success' => false,
                    'error'   => "No se pudo conectar al servidor SMTP ({$this->host}:{$this->port}): [{$errno}] {$errstr}",
                ];
            }

            stream_set_timeout($socket, $this->timeout);

            // Saludo inicial
            $res = $this->readResponse($socket);
            if ((int) substr($res, 0, 3) !== 220) {
                fclose($socket);
                return ['success' => false, 'error' => 'Respuesta inesperada del servidor SMTP: ' . $res];
            }

            // EHLO
            $hostname = gethostname() ?: 'localhost';
            $this->sendCommand($socket, "EHLO {$hostname}", [250]);

            // STARTTLS para puerto 587
            if ($this->port === 587) {
                $this->sendCommand($socket, 'STARTTLS', [220]);
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    fclose($socket);
                    return ['success' => false, 'error' => 'Fallo al establecer túnel TLS con el servidor SMTP.'];
                }
                $this->sendCommand($socket, "EHLO {$hostname}", [250]);
            }

            // Autenticación AUTH LOGIN si se suministraron credenciales
            if ($this->username !== '' && $this->password !== '') {
                $this->sendCommand($socket, 'AUTH LOGIN', [334]);
                $this->sendCommand($socket, base64_encode($this->username), [334]);
                $this->sendCommand($socket, base64_encode($this->password), [235]);
            }

            // Remitente y destinatario
            $sender = $this->fromEmail !== '' ? $this->fromEmail : 'cyberpos@lanerosgamer.com';
            $this->sendCommand($socket, "MAIL FROM:<{$sender}>", [250]);
            $this->sendCommand($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
            $this->sendCommand($socket, 'DATA', [354]);

            // Determinar MIME type del adjunto
            $isXlsx = (substr(strtolower($attachmentName), -5) === '.xlsx');
            $mimeType = $isXlsx
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/pdf';

            // Construir mensaje MIME multipart
            $boundary = '=_CyberPOS_' . md5(uniqid((string) mt_rand(), true));
            $subjectB64 = '=?UTF-8?B?' . base64_encode($subject) . '?=';

            $headers  = "From: {$this->fromName} <{$sender}>\r\n";
            $headers .= "To: <{$toEmail}>\r\n";
            $headers .= "Subject: {$subjectB64}\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

            // Parte HTML
            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

            // Parte Adjunto si existe
            if ($attachmentData !== null && $attachmentData !== '') {
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: {$mimeType}; name=\"{$attachmentName}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$attachmentName}\"\r\n\r\n";
                $body .= chunk_split(base64_encode($attachmentData)) . "\r\n";
            }

            $body .= "--{$boundary}--\r\n";

            // Enviar cuerpo y finalizar DATA con punto
            fwrite($socket, $headers . $body . "\r\n.\r\n");
            $dataRes = $this->readResponse($socket);
            if ((int) substr($dataRes, 0, 3) !== 250) {
                fclose($socket);
                return ['success' => false, 'error' => 'Error al transmitir datos del correo: ' . $dataRes];
            }

            // QUIT
            $this->sendCommand($socket, 'QUIT', [221]);
            fclose($socket);

            return [
                'success' => true,
                'message' => 'Reporte gerencial enviado exitosamente a ' . $toEmail . '.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'Error en cliente SMTP: ' . $e->getMessage(),
            ];
        }
    }
}
