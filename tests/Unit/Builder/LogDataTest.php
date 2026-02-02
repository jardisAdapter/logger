<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Builder;

use JardisAdapter\Logger\Builder\LogData;
use PHPUnit\Framework\TestCase;

class LogDataTest extends TestCase
{
    public function testConstructorInitializesDefaults(): void
    {
        $logData = new LogData();

        $expectedRecordLogData = [
            LogData::CONTEXT => '',
            LogData::LEVEL => '',
            LogData::MESSAGE => '',
        ];

        $this->assertSame($expectedRecordLogData, $this->getPrivateProperty($logData, 'recordLogData'));
        $this->assertSame([], $this->getPrivateProperty($logData, 'additionalUserLogData'));
    }

    public function testConstructorInitializesWithCustomValues(): void
    {
        $additionalRecordLogData = ['customKey' => 'customValue'];
        $additionalUserLogData = ['userKey' => 'userValue'];

        $logData = new LogData($additionalRecordLogData, $additionalUserLogData);

        $expectedRecordLogData = [
            'customKey' => 'customValue',
            LogData::CONTEXT => '',
            LogData::LEVEL => '',
            LogData::MESSAGE => '',
        ];

        $this->assertSame($expectedRecordLogData, $this->getPrivateProperty($logData, 'recordLogData'));
        $this->assertSame($additionalUserLogData, $this->getPrivateProperty($logData, 'additionalUserLogData'));
    }

    public function testInvokeGeneratesLogDataWithCorrectValues(): void
    {
        $logData = new LogData();

        $result = $logData('testContext', 'info', 'Hello {name} mit {json}!', ['name' => 'World', 'json' => ['content']]);

        $this->assertSame('testContext', $result[LogData::CONTEXT]);
        $this->assertSame('info', $result[LogData::LEVEL]);
        $this->assertSame('Hello World mit ["content"]!', $result[LogData::MESSAGE]);
    }

    public function testAddFieldAddsNewField(): void
    {
        $logData = new LogData();
        $logData->addField('newField', fn() => 'computedValue');

        $recordLogData = $this->getPrivateProperty($logData, 'recordLogData');
        $this->assertTrue(array_key_exists('newField', $recordLogData));
        $this->assertEquals('computedValue', $recordLogData['newField']());
    }

    public function testAddFieldDoesNotOverwriteExistingField(): void
    {
        $logData = new LogData();
        $logData->addField('existingField', fn() => 'initialValue');
        $logData->addField('existingField', fn() => 'newValue');

        $recordLogData = $this->getPrivateProperty($logData, 'recordLogData');
        $this->assertEquals('initialValue', $recordLogData['existingField']());
    }

    public function testAddExtraAddsNewField(): void
    {
        $logData = new LogData();
        $logData->addExtra('userField', fn() => 'userValue');

        $userLogData = $this->getPrivateProperty($logData, 'additionalUserLogData');
        $this->assertTrue(array_key_exists('userField', $userLogData));
        $this->assertEquals('userValue', $userLogData['userField']());
    }

    public function testAddExtraDoesNotOverwriteExistingField(): void
    {
        $logData = new LogData();
        $logData->addExtra('userField', fn() => 'initialValue');
        $logData->addExtra('userField', fn() => 'newValue');

        $userLogData = $this->getPrivateProperty($logData, 'additionalUserLogData');
        $this->assertEquals('initialValue', $userLogData['userField']());
    }

    public function testInterpolateReplacesPlaceholdersCorrectly(): void
    {
        $logData = new LogData();
        $result = $this->callPrivateMethod($logData, 'interpolate', ['{callable} hello {name}!', ['name' => 'world', 'callable' => fn() => 'Call']]);

        $this->assertSame('Call hello world!', $result);
    }

    public function testInterpolateDoesNotReplaceMissingPlaceholders(): void
    {
        $logData = new LogData();
        $result = $this->callPrivateMethod($logData, 'interpolate', ['Hello {name}!', []]);

        $this->assertSame('Hello {name}!', $result);
    }

    public function testInterpolateLeavesMessagesWithoutPlaceholdersUnchanged(): void
    {
        $logData = new LogData();
        $result = $this->callPrivateMethod($logData, 'interpolate', ['Hello World!', []]);

        $this->assertSame('Hello World!', $result);
    }

    public function testInvokeWithAddFieldIncludesFieldInRootLevel(): void
    {
        $logData = new LogData();
        $logData->addField('customField', fn() => 'customValue');

        $result = $logData('testContext', 'info', 'Test message', []);

        $this->assertArrayHasKey('customField', $result);
        $this->assertSame('customValue', $result['customField']);
    }

    public function testInvokeWithAddExtraIncludesFieldInDataArray(): void
    {
        $logData = new LogData();
        $logData->addExtra('extraField', fn() => 'extraValue');

        $result = $logData('testContext', 'info', 'Test message', ['userKey' => 'userValue']);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('extraField', $result['data']);
        $this->assertSame('extraValue', $result['data']['extraField']);
        $this->assertArrayHasKey('userKey', $result['data']);
        $this->assertSame('userValue', $result['data']['userKey']);
    }

    public function testInvokeWithAddFieldAndAddExtroCombined(): void
    {
        $logData = new LogData();
        $logData->addField('rootField', fn() => 'rootValue');
        $logData->addExtra('extraField', fn() => 'extraValue');

        $result = $logData('testContext', 'info', 'Test message', ['userKey' => 'userValue']);

        // Root field should be at top level
        $this->assertArrayHasKey('rootField', $result);
        $this->assertSame('rootValue', $result['rootField']);

        // Extra field should be in data array
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('extraField', $result['data']);
        $this->assertSame('extraValue', $result['data']['extraField']);
        $this->assertArrayHasKey('userKey', $result['data']);
    }

    public function testInterpolationCanUseFieldsFromAddField(): void
    {
        $logData = new LogData();
        $logData->addField('timestamp', fn() => '2025-11-30');

        $result = $logData('testContext', 'info', 'Log at {timestamp}', []);

        $this->assertSame('Log at 2025-11-30', $result['message']);
    }

    public function testAddFieldReturnsInstanceForMethodChaining(): void
    {
        $logData = new LogData();
        $returnValue = $logData->addField('field1', fn() => 'value1');

        $this->assertSame($logData, $returnValue);
    }

    public function testAddExtraReturnsInstanceForMethodChaining(): void
    {
        $logData = new LogData();
        $returnValue = $logData->addExtra('field1', fn() => 'value1');

        $this->assertSame($logData, $returnValue);
    }

    public function testMethodChainingWorksWithMultipleCalls(): void
    {
        $logData = new LogData();
        $result = $logData
            ->addField('field1', fn() => 'value1')
            ->addField('field2', fn() => 'value2')
            ->addExtra('extra1', fn() => 'extraValue1')
            ->addExtra('extra2', fn() => 'extraValue2');

        $this->assertSame($logData, $result);

        $record = $logData('ctx', 'info', 'msg', []);
        $this->assertArrayHasKey('field1', $record);
        $this->assertArrayHasKey('field2', $record);
        $this->assertArrayHasKey('extra1', $record['data']);
        $this->assertArrayHasKey('extra2', $record['data']);
    }

    /**
     * Helper method to access private properties for testing.
     */
    private function getPrivateProperty(object $object, string $property)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Helper method to call private methods for testing.
     */
    private function callPrivateMethod(object $object, string $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
