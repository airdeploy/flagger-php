<?php declare(strict_types=1);

namespace Flagger;

use PHPUnit\Framework\TestCase;


final class FlaggerTest extends TestCase
{
    private $flagKillSwitchEnabled = 'expiring-photo-sharing';
    private $flagCodenameWhitelisted = 'group-messaging';
    private $flagWhitelistUserID = '57145770';
    private $flagWhitelistUserVariation = 'enabled';
    private $flagWhitelistCompanyID = '71904530';
    private $flagWhitelistCompanyVariation = 'enabled';
    private $flagCodenameBlacklisted = 'color-theme';
    private $flagBlacklistUserID = '83367660';
    private $flagCodenamePayload = 'faq-redesign';
    private $flagCodenameWithFilters = "color-theme";
    private $entityTypeFilter = "User";

    private static $serverUrl;
    private static $flagIdWhitelisted;
    private static $apiKey;
    private static $envId;
    private static $accessToken;

    public static function setUpBeforeClass(): void
    {
        self::$apiKey = getenv('API_KEY') ?: 's2wd43udhhz6fbqh';
        self::$accessToken = getenv('ACCESS_TOKEN') ?: '';
        self::$envId = getenv('ENV_ID') ?: '2806';
        self::$serverUrl = getenv('SERVER_URL') ?: 'http://localhost:3000';

        Flagger::init(['apiKey' => self::$apiKey,
            'sourceURL' => self::$serverUrl . "/v3/config/",
            'sseURL' => self::$serverUrl . "/v3/sse/",
            'ingestionURL' => self::$serverUrl . "/v3/ingest/",
        ]);

        self::$flagIdWhitelisted = getenv('FLAG_ID_WHITELISTED') ?: '1';
    }

    public function testKillSwitch(): void
    {
        $this->assertFalse(Flagger::isEnabled($this->flagKillSwitchEnabled, ["id" => "3201"]));
    }

    public function testEmptyCodename(): void
    {
        $this->assertFalse(Flagger::isEnabled("", ["id" => "3201"]));
        $this->assertFalse(Flagger::isSampled("", ["id" => "3201"]));
        $this->assertEquals("off", Flagger::getVariation("", ["id" => "3201"]));
        $this->assertEquals([], Flagger::getPayload("", ["id" => "3201"]));
    }

    public function testEmptyId(): void
    {
        $this->assertFalse(Flagger::isEnabled("test", ["id" => ""]));
        $this->assertFalse(Flagger::isSampled("test", ["id" => ""]));
        $this->assertEquals("off", Flagger::getVariation("test", ["id" => ""]));
        $this->assertEquals([], Flagger::getPayload("test", ["id" => ""]));
    }

    public function testPublishEmptyId(): void
    {
        $this->expectNotToPerformAssertions();
        Flagger::publish(['id' => '']);
    }

    public function testPublish(): void
    {
        $this->expectNotToPerformAssertions();
        Flagger::publish([
            "type" => "User",
            "id" => "1234",
            "name" => "ironman@stark.com",
            "attributes" => [
                "tShirtSize" => "M",
                "dateCreated" => "2018-02-18",
                "timeConverted" => "2018-02-20T21:54=>00.630815+00:00",
                "ownsProperty" => True,
                "age" => 39
            ],
            "group" => [
                "type" => "Club",
                "id" => "5678",
                "name" => "Avengers Club",
                "attributes" => [
                    "founded" => "2016-01-01",
                    "active" => True
                ]
            ]
        ]);
    }

    public function testTrackEmpty(): void
    {
        $this->expectNotToPerformAssertions();
        //empty event name
        Flagger::track('', ["test" => true], ['id' => '1']);

        //empty entity id
        Flagger::track('event-name', ["test" => true], ['id' => '']);
    }

    public function testSetEntityEmptyId(): void
    {
        $this->expectNotToPerformAssertions();
        Flagger::setEntity(['id' => '']);
    }

    public function testWhitelist(): void
    {
        $this->assertTrue(Flagger::isEnabled($this->flagCodenameWhitelisted, ["id" => $this->flagWhitelistUserID]));
        $this->assertTrue(Flagger::isEnabled($this->flagCodenameWhitelisted,
            ["id" => '3306', 'group' => ['id' => $this->flagWhitelistCompanyID, 'type' => 'Company']]));

        self::assertEquals($this->flagWhitelistUserVariation,
            Flagger::getVariation($this->flagCodenameWhitelisted, ['id' => $this->flagWhitelistUserID]));

        self::assertEquals($this->flagWhitelistCompanyVariation,
            Flagger::getVariation($this->flagCodenameWhitelisted,
                ["id" => '3306', 'group' => ['id' => $this->flagWhitelistCompanyID, 'type' => 'Company']]));
    }

    public function testBlacklist(): void
    {
        $this->assertFalse(Flagger::isEnabled($this->flagCodenameBlacklisted, ["id" => $this->flagBlacklistUserID]));
        $this->assertFalse(Flagger::isEnabled($this->flagCodenameBlacklisted,
            ["id" => '3306', 'group' => ['id' => $this->flagBlacklistUserID, 'type' => 'User']]));

    }

    public function testFilters(): void
    {
        $this->assertTrue(Flagger::isEnabled($this->flagCodenameWithFilters,
            ["id" => '344', 'type' => $this->entityTypeFilter, 'attributes' => ["createdAt" => "2014-09-20T00:00:00Z"]]));

        $this->assertTrue(Flagger::isEnabled($this->flagCodenameWithFilters,
            ["id" => '344', 'type' => $this->entityTypeFilter, 'attributes' =>
                [
                    "createdAt" => "2014-09-20T00:00:00Z",
                    "age" => 9007199254740991,
                    "ignored" => ["wrongType" => True],
                    12 => "hello"
                ]]));
    }

    public function testSSE(): void
    {
        $entity = ['id' => $this->flagWhitelistUserID];
        self::assertTrue(Flagger::isEnabled($this->flagCodenameWhitelisted, $entity));

        $this->setKillSwitch(FlaggerTest::$flagIdWhitelisted, true);

        sleep(1);
        $enabled = Flagger::isEnabled($this->flagCodenameWhitelisted, $entity);

        $this->setKillSwitch(FlaggerTest::$flagIdWhitelisted, false);
        sleep(1);
        self::assertFalse($enabled);

    }


    public function testSetEntity(): void
    {
        $entity = ['id' => $this->flagWhitelistUserID];
        self::assertTrue(Flagger::isEnabled($this->flagCodenameWhitelisted, $entity));

        Flagger::setEntity($entity);
        self::assertTrue(Flagger::isEnabled($this->flagCodenameWhitelisted));

        //clearing things out
        Flagger::setEntity();
    }

    public function testShutdown(): void
    {
        self::assertTrue(Flagger::isEnabled($this->flagCodenameWhitelisted, ['id' => $this->flagWhitelistUserID]));

        self::assertFalse(Flagger::shutdown(1000));

        self::assertFalse(Flagger::isEnabled($this->flagCodenameWhitelisted, ['id' => $this->flagWhitelistUserID]));

    }

    private function setKillSwitch($flagId, $killSwitch): void
    {
        $url = FlaggerTest::$serverUrl . "/graphql";
        $data = ["operationName" => "ToggleFlags",
            "variables" => ["toggleInput" => [["flagId" => (int)$flagId, "killSwitchEngaged" => $killSwitch]],
                "environmentId" => FlaggerTest::$envId,
                "message" => ""],
            "query" => 'mutation ToggleFlags($toggleInput: [ToggleInput!]!, $environmentId: Int!, $message: String!) {\n  toggleFlags(toggleInput: $toggleInput, environmentId: $environmentId, message: $message) {\n    id\n    __typename\n  }\n}\n'];

        $options = array(
            'http' => array(
                'header' => ["Content-type:application/json", "Authorization: Bearer " . FlaggerTest::$accessToken],
                'method' => 'POST',
                'content' => json_encode($data)
            )
        );
        $context = stream_context_create($options);
        file_get_contents($url, false, $context);
    }

}