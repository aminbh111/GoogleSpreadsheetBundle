<?php

namespace Dreamlex\Bundle\GoogleSpreadsheetBundle\Spreadsheet;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Class GoogleSpreadsheet
 *
 * @package Dreamlex\Bundle\GoogleSpreadsheetBundle\Spreadsheet
 */
class GoogleSpreadsheet {

    /**
     * @var string
     */
    protected $kernelRootDir;

    /**
     * @var string
     */
    protected $appName;

    /**
     * @var string
     */
    protected $credentialsFilename;

    /**
     * @var \Google_Client
     */
    protected $client;
    protected $scopes = [
        'readonly' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
        'readwrite' => "https://www.googleapis.com/auth/spreadsheets"
    ];

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var boolean
     */
    protected $isAuthorized;

    /**
     * GoogleSpreadsheet constructor.
     *
     * @param string $kernelRootDir
     * @param string $appName
     * @param string $scope
     * @param string $authConfigPath
     *
     * @throws \InvalidArgumentException
     * @throws \Google_Exception
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    public function __construct($kernelRootDir, $appName, $scope = 'readonly', $authConfigPath = null) {
        if (false === array_key_exists($scope, $this->scopes)) {
            throw new \InvalidArgumentException('Unknown scope');
        }

        $this->appName = $appName;
        $this->kernelRootDir = $kernelRootDir;
        $this->credentialsFilename = $kernelRootDir . '/config/credentials/' . $this->appName . '.json';
        $this->scope = $scope;

        if ($authConfigPath === null) {
            $authConfigPath = $this->kernelRootDir . '/config/client_secret.json';
        }

        $this->fs = new Filesystem();

        $this->client = new \Google_Client();
        $this->client->setApplicationName($appName);
        $this->client->setScopes($this->scopes[$scope]);
        $this->client->setAuthConfigFile($authConfigPath);
        $this->client->setAccessType('offline');
        $this->isAuthorized = false;
    }

    /**
     * @param string $spreadsheetId Such as 13O_57K1FCSYVnI0oMESfqLx7_yPP3vNVuSjPuc75Fus
     * @param string $range         Range
     *
     * @return mixed
     */
    public function get($spreadsheetId, $range = null) {
        $service = new \Google_Service_Sheets($this->getAuthorizedClient());
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        return $response->getValues();
    }

    /**
     * @param string $spreadsheetId Such as 13O_57K1FCSYVnI0oMESfqLx7_yPP3vNVuSjPuc75Fus
     * @param string $range         Range
     *
     * @return mixed
     */
    public function append($spreadsheetId, $range = null, $values = null) {
        $service = new \Google_Service_Sheets($this->getAuthorizedClient());
        $optParams['insertDataOption'] = 'INSERT_ROWS';
        $optParams['valueInputOption'] = 'RAW';
        $requestBody = new \Google_Service_Sheets_ValueRange();
        $requestBody->setValues(array("values" => $values));
        $response = $service->spreadsheets_values->append($spreadsheetId, $range, $requestBody, $optParams);
        return $response->getUpdates();
    }

    /**
     * @param string $spreadsheetId Such as 13O_57K1FCSYVnI0oMESfqLx7_yPP3vNVuSjPuc75Fus
     * @param string $range         Range
     *
     * @return mixed
     */
    public function clear($spreadsheetId, $range = null) {
        $service = new \Google_Service_Sheets($this->getAuthorizedClient());
        $requestBody = new \Google_Service_Sheets_ClearValuesRequest();
        $response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);
        return $response->getClearedRange();
    }

    /**
     * @return \Google_Client the authorized client object
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * @return string
     */
    public function getCredentialsFilename(): string {
        return $this->credentialsFilename;
    }

    /**
     * @param array $accessToken
     *
     * @return string
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    public function saveCredentials(array $accessToken) {
        $this->fs = new Filesystem();

        $this->fs->dumpFile($this->credentialsFilename, json_encode($accessToken));

        return $this->credentialsFilename;
    }

    /**
     * @return bool
     */
    public function isCredentialsExisted() {
        $this->fs = new Filesystem();

        return $this->fs->exists($this->credentialsFilename);
    }

    /**
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    public function removeCredentials() {
        $this->fs = new Filesystem();

        $this->fs->remove($this->credentialsFilename);
    }

    /**
     * Refresh the token if it's expired.
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    protected function refreshToken() {
        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            $this->fs->dumpFile($this->credentialsFilename, json_encode($this->client->getAccessToken()));
        }
    }

    /**
     * @return \Google_Client
     */
    protected function getAuthorizedClient() {
        if (false === $this->isAuthorized) {
            if (false === $this->isCredentialsExisted()) {
                throw new \BadMethodCallException('No credentials found');
            }

            $accessToken = json_decode(file_get_contents($this->credentialsFilename), true);
            $this->client->setAccessToken($accessToken);
            $this->refreshToken();
            $this->isAuthorized = true;
        }
        return $this->client;
    }

}
