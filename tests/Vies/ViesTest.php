<?php
namespace DragonBe\Test\Vies;

use DragonBe\Vies\Vies;

class ViestTest extends \PHPUnit_Framework_TestCase
{
    public function vatNumberProvider()
    {
        return array (
            array ('0123456789','0123456789'),
            array ('0123 456 789','0123456789'),
            array ('0123.456.789','0123456789'),
            array ('0123-456-789','0123456789'),
        );
    }
    /**
     * @dataProvider vatNumberProvider
     * @covers \DragonBe\Vies\Vies::filterVat
     */
    public function testVatNumberFilter($vatNumber, $filteredNumber)
    {
        $this->assertEquals($filteredNumber,
            Vies::filterVat($vatNumber));
    }

    protected function _createdStubbedViesClient($response)
    {
        $stub = $this->getMockFromWsdl(
            dirname(__FILE__) . '/_files/checkVatService.wsdl');
        $stub->expects($this->any())
             ->method('__soapCall')
             ->will($this->returnValue($response));

        $vies = new Vies();
        $vies->setSoapClient($stub);
        return $vies;
    }

    /**
     * @covers \DragonBe\Vies\Vies::validateVat
     * @covers \DragonBe\Vies\Vies::setSoapClient
     */
    public function testSuccessVatNumberValidation()
    {
        $response = new \StdClass();
        $response->countryCode = 'BE';
        $response->vatNumber = '0123.456.789';
        $response->requestDate = '1983-06-24+23:59';
        $response->valid = true;
        $response->name = '';
        $response->address = '';
        
        $vies = $this->_createdStubbedViesClient($response);
        
        $response = $vies->validateVat('BE', '0123.456.789');
        $this->assertInstanceOf('\\DragonBe\\Vies\\CheckVatResponse', $response);
        $this->assertTrue($response->isValid());
        return $response;
    }

    /**
     * @covers \DragonBe\Vies\Vies::validateVat
     * @covers \DragonBe\Vies\Vies::setSoapClient
     */
    public function testFailureVatNumberValidation()
    {
        $response = new \StdClass();
        $response->countryCode = 'BE';
        $response->vatNumber = '0123.ABC.789';
        $response->requestDate = '1983-06-24+23:59';
        $response->valid = false;
        
        $vies = $this->_createdStubbedViesClient($response);

        $response = $vies->validateVat('BE', '0123.ABC.789');
        $this->assertInstanceOf('\\DragonBe\\Vies\\CheckVatResponse', $response);
        $this->assertFalse($response->isValid());
    }

    public function badCountryCodeProvider()
    {
        return [
            ['AA'],
            ['TK'],
            ['PH'],
            ['FS'],
        ];
    }
    /**
     * Test to see the country code is rejected if not existing in the EU
     *
     * @dataProvider badCountryCodeProvider
     * @covers \DragonBe\Vies\Vies::validateVat
     * @expectedException \DragonBe\Vies\ViesException
     * @param $code
     */
    public function testExceptionIsRaisedForNonEuropeanUnionCountryCodes($code)
    {
        $vies = new Vies();
        $vies->validateVat($code, 'does not matter');
    }

    /**
     * @param \DragonBe\Vies\CheckVatResponse $response
     * @depends testSuccessVatNumberValidation
     * @covers \DragonBe\Vies\CheckVatResponse::toArray
     */
    public function testConvertObjectIntoArray($response)
    {
        $array = $response->toArray();
        $this->assertSame('BE', $array['countryCode']);
        $this->assertSame('0123.456.789', $array['vatNumber']);
        $this->assertSame('1983-06-24', $array['requestDate']);
        $this->assertTrue($array['valid']);
        $this->assertEmpty($array['name']);
        $this->assertEmpty($array['address']);
    }

    private function createHeartBeatMock($bool)
    {
        $heartBeatMock = $this->getMock(
            '\\DragonBe\\Vies\\HeartBeat',
            array ('isAlive')
        );
        $heartBeatMock->expects($this->once())
            ->method('isAlive')
            ->will($this->returnValue($bool));
        return $heartBeatMock;
    }

    /**
     * @covers \DragonBe\Vies\Vies::getHeartBeat
     * @covers \DragonBe\Vies\Vies::setHeartBeat
     */
    public function testServiceIsAlive()
    {
        $vies = new Vies();
        $hb = $this->createHeartBeatMock(true);
        $vies->setHeartBeat($hb);
        $this->assertTrue(
            $vies->getHeartBeat()->isAlive()
        );
    }

    /**
     * @covers \DragonBe\Vies\Vies::getHeartBeat
     * @covers \DragonBe\Vies\Vies::setHeartBeat
     */
    public function testServiceIsDown()
    {
        $vies = new Vies();
        $hb = $this->createHeartBeatMock(false);
        $vies->setHeartBeat($hb);
        $this->assertFalse(
            $vies->getHeartBeat()->isAlive()
        );
    }

    /**
     * @covers \DragonBe\Vies\Vies::getSoapClient
     * @todo: SoapClient connects to european commission VIES service at initialisation
     */
    public function testGettingDefaultSoapClient()
    {
        if (0 >= strpos(phpversion(), 'HipHop') && !extension_loaded('soap')) {
            $this->markTestSkipped('SOAP not installed');
        }
        $vies = new Vies();
        $vies->setSoapClient($this->_createdStubbedViesClient('blabla')->getSoapClient());
        $soapClient = $vies->getSoapClient();
        $expected = '\\SoapClient';
        $this->assertInstanceOf($expected, $soapClient);
    }

    /**
     * @covers \DragonBe\Vies\Vies::getSoapClient
     */
    public function testDefaultSoapClientIsLazyLoaded()
    {
        if (0 >= strpos(phpversion(), 'HipHop') && !extension_loaded('soap')) {
            $this->markTestSkipped('SOAP not installed');
        }
        $wsdl = dirname(__FILE__) . '/_files/checkVatService.wsdl';
        $vies = new Vies();
        $vies->setWsdl($wsdl);
        $this->assertInstanceOf('\\SoapClient', $vies->getSoapClient());
    }

    /**
     * @covers \DragonBe\Vies\Vies::setOptions
     * @covers \DragonBe\Vies\Vies::getOptions
     */
    public function testOptionsCanBeSet()
    {
        $options = ['foo' => 'bar'];
        $vies = new Vies();
        $vies->setOptions($options);
        $this->assertSame($options, $vies->getOptions());
    }

    /**
     * @covers \DragonBe\Vies\Vies::getWsdl
     */
    public function testGettingDefaultWsdl()
    {
        $vies = new Vies();
        $wsdl = $vies->getWsdl();
        $expected = sprintf(
            '%s://%s%s',
            Vies::VIES_PROTO,
            Vies::VIES_DOMAIN,
            Vies::VIES_WSDL
        );
        $this->assertSame($expected, $wsdl);
    }

    /**
     * @covers \DragonBe\Vies\Vies::setWsdl
     */
    public function testSettingCustomWsdl()
    {
        $wsdl = 'http://www.example.com/?wsdl';
        $vies = new Vies();
        $vies->setWsdl($wsdl);
        $actual = $vies->getWsdl();
        $this->assertSame($wsdl, $actual);
    }

    /**
     * @covers \DragonBe\Vies\Vies::setOptions
     * @covers \DragonBe\Vies\Vies::getOptions
     */
    public function testSettingSoapOptions()
    {
        if (0 >= strpos(phpversion(), 'HipHop')) {
            $this->markTestSkipped('This test does not work for HipHop VM');
        }
        $options = array (
            'soap_version' => SOAP_1_2,
        );
        $vies = new Vies();
        $vies->setSoapClient($this->_createdStubbedViesClient('blabla')->getSoapClient());
        $vies->setOptions($options);
        $soapClient = $vies->getSoapClient();
        $actual = $soapClient->_soap_version;
        $this->assertSame($options['soap_version'], $actual);
        $this->assertSame($options, $vies->getOptions());
    }

    /**
     * @covers \DragonBe\Vies\Vies::getOptions
     */
    public function testDefaultOptionsAreEmpty()
    {
        $vies = new Vies();
        $options = $vies->getOptions();
        $this->assertInternalType('array', $options);
        $this->assertEmpty($options);
    }

    /**
     * @covers \DragonBe\Vies\Vies::getHeartBeat
     */
    public function testGetDefaultHeartBeatWhenNoneSpecified()
    {
        $vies = new Vies();
        $hb = $vies->getHeartBeat();
        $this->assertInstanceOf('\\DragonBe\\Vies\\HeartBeat', $hb);

        $this->assertSame('tcp://' . Vies::VIES_DOMAIN, $hb->getHost());
        $this->assertSame(80, $hb->getPort());
    }

    /**
     * @covers \DragonBe\Vies\Vies::listEuropeanCountries
     */
    public function testRetrievingListOfEuropeanCountriesStatically()
    {
        $countryList = Vies::listEuropeanCountries();
        $this->assertCount(Vies::VIES_EU_COUNTRY_TOTAL, $countryList);
    }
}
