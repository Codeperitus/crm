<?php

namespace OroCRM\Bundle\ChannelBundle\Tests\Unit\Form\Type;

use Genemu\Bundle\FormBundle\Form\JQuery\Type\Select2Type;

use Symfony\Component\Validator\Validator;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\ClassMetadataFactory;
use Symfony\Component\Form\Extension\Csrf\Type\FormTypeCsrfExtension;
use Symfony\Component\Form\Extension\Validator\Type\FormTypeValidatorExtension;

use Oro\Bundle\UserBundle\Form\Type\UserAclSelectType;
use Oro\Bundle\UserBundle\Form\Type\OrganizationUserAclSelectType;
use Oro\Bundle\FormBundle\Autocomplete\SearchRegistry;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Bundle\IntegrationBundle\Form\Type\ChannelType;
use Oro\Bundle\FormBundle\Form\Extension\TooltipFormExtension;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\FormBundle\Form\Type\OroJquerySelect2HiddenType;
use Oro\Bundle\IntegrationBundle\Form\Type\IntegrationTypeSelectType;
use Oro\Bundle\FormBundle\Form\Type\OroEntitySelectOrCreateInlineType;
use Oro\Bundle\IntegrationBundle\Form\EventListener\ChannelFormSubscriber;

use OroCRM\Bundle\ChannelBundle\Form\Type\ChannelDatasourceType;
use OroCRM\Bundle\ChannelBundle\Form\Extension\IntegrationTypeExtension;

class ChannelDatasourceTypeTest extends FormIntegrationTestCase
{
    const TEST_ID             = 1;
    const TEST_NAME           = 'name';
    const TEST_TYPE           = 'type';
    const TEST_ID_FIELD_NAME  = 'id';
    const TEST_SUBMITTED_NAME = 'nameSubmitted';
    const TEST_CHANNEL_TYPE   = 'channelType';

    /** @var ChannelDatasourceType */
    protected $type;

    /** @var ManagerRegistry|\PHPUnit_Framework_MockObject_MockObject */
    protected $registry;

    /** @var string */
    protected $testEntityName = 'OroIntegration:Channel';

    public function setUp()
    {
        parent::setUp();
        $this->registry = $this->getMockBuilder('Symfony\Bridge\Doctrine\ManagerRegistry')
            ->disableOriginalConstructor()->getMock();

        $this->type = new ChannelDatasourceType($this->registry, $this->testEntityName);
    }

    protected function getExtensions()
    {
        $assetsHelper    = $this->getMockBuilder('Symfony\Component\Templating\Helper\CoreAssetsHelper')
            ->disableOriginalConstructor()->getMock();
        $integrationType = $this->getMock('Oro\Bundle\IntegrationBundle\Provider\ChannelInterface');
        $transportType   = $this->getMock('Oro\Bundle\IntegrationBundle\Provider\TransportInterface');
        $registry        = new TypesRegistry();
        $registry->addChannelType(self::TEST_TYPE, $integrationType);
        $registry->addTransportType(uniqid('transport'), self::TEST_TYPE, $transportType);

        $security = $this->getMockBuilder('Oro\Bundle\SecurityBundle\SecurityFacade')
            ->disableOriginalConstructor()->getMock();

        $em       = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()->getMock();
        $metadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()->getMock();
        $em->expects($this->once())->method('getClassMetadata')
            ->with($this->equalTo('OroUser:User'))
            ->will($this->returnValue($metadata));
        $metadata->expects($this->once())->method('getSingleIdentifierFieldName')
            ->will($this->returnValue(self::TEST_ID_FIELD_NAME));
        $searchHandler = $this->getMock('Oro\Bundle\FormBundle\Autocomplete\SearchHandlerInterface');
        $searchHandler->expects($this->any())->method('getEntityName')
            ->will($this->returnValue('OroUser:User'));
        $searchRegistry = new SearchRegistry();
        $searchRegistry->addSearchHandler('acl_users', $searchHandler);

        $config = $this->getMock('Oro\Bundle\EntityConfigBundle\Config\ConfigInterface');
        $config->expects($this->any())->method('has')->with($this->equalTo('grid_name'))
            ->will($this->returnValue(true));
        $config->expects($this->any())->method('get')->with($this->equalTo('grid_name'))
            ->will($this->returnValue('test_grid'));
        $cp = $this->getMock('Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface');
        $cp->expects($this->any())->method('getConfig')->will($this->returnValue($config));
        $cm = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigManager')
            ->disableOriginalConstructor()->getMock();
        $cm->expects($this->any())->method('getProvider')->will($this->returnValue($cp));

        $validator = new Validator(
            new ClassMetadataFactory(new LoaderChain([])),
            new ConstraintValidatorFactory(),
            new DefaultTranslator()
        );

        $settingsProvider = $this->getMockBuilder('OroCRM\Bundle\ChannelBundle\Provider\SettingsProvider')
            ->disableOriginalConstructor()->getMock();

        return [
            new PreloadedExtension(
                [
                    'oro_integration_channel_form'       => $this->getChannelType($registry),
                    'oro_integration_type_select'        => new IntegrationTypeSelectType($registry, $assetsHelper),
                    'oro_user_organization_acl_select'   => new OrganizationUserAclSelectType(),
                    'oro_user_acl_select'                => new UserAclSelectType(),
                    'oro_entity_create_or_select_inline' => new OroEntitySelectOrCreateInlineType($security, $cm),
                    'oro_jqueryselect2_hidden'           => new OroJquerySelect2HiddenType($em, $searchRegistry, $cp),
                    'genemu_jqueryselect2_choice'        => new Select2Type('choice'),
                    'genemu_jqueryselect2_hidden'        => new Select2Type('hidden')
                ],
                [
                    'form' => [
                        new FormTypeCsrfExtension(
                            $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface')
                        ),
                        new FormTypeValidatorExtension($validator),
                        new TooltipFormExtension(),
                    ],
                    'oro_integration_channel_form' => [
                        new IntegrationTypeExtension($settingsProvider)
                    ]
                ]
            )
        ];
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->type, $this->registry, $this->testEntityName);
    }

    public function testFormSubmit()
    {
        $this->prepareEmMock();

        $form = $this->factory->create(
            $this->type,
            null,
            [
                'type'            => self::TEST_CHANNEL_TYPE,
                'csrf_protection' => false
            ]
        );
        $form->submit(
            [
                'identifier' => self::TEST_ID,
                'data'       => json_encode(['name' => self::TEST_SUBMITTED_NAME, 'type' => self::TEST_TYPE])
            ]
        );

        /** @var Integration $integration */
        $integration = $form->getData();
        $viewData    = $form->getViewData();

        $this->assertSame(self::TEST_TYPE, $integration->getType());
        $this->assertSame(self::TEST_SUBMITTED_NAME, $integration->getName());

        $this->assertSame(
            [
                'type'       => self::TEST_TYPE,
                'data'       => null,
                'identifier' => $integration,
                'name'       => self::TEST_SUBMITTED_NAME
            ],
            $viewData
        );
    }

    protected function prepareEmMock()
    {
        $em       = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()->getMock();
        $metadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()->getMock();
        $repo     = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()->getMock();
        $entity   = new Integration();
        $entity->setName(self::TEST_NAME);
        $entity->setType(self::TEST_TYPE);

        $this->registry->expects($this->once())->method('getManagerForClass')
            ->with($this->equalTo($this->testEntityName))
            ->will($this->returnValue($em));
        $em->expects($this->once())->method('getClassMetadata')
            ->with($this->equalTo($this->testEntityName))
            ->will($this->returnValue($metadata));
        $metadata->expects($this->once())->method('getSingleIdentifierFieldName')
            ->will($this->returnValue(self::TEST_ID_FIELD_NAME));
        $em->expects($this->once())->method('getRepository')
            ->with($this->equalTo($this->testEntityName))
            ->will($this->returnValue($repo));
        $repo->expects($this->once())->method('find')
            ->with($this->equalTo(self::TEST_ID))
            ->will($this->returnValue($entity));
    }

    /**
     * @param TypesRegistry $registry
     *
     * @return ChannelType
     */
    protected function getChannelType(TypesRegistry $registry)
    {
        $settingsProvider = $this->getMockBuilder('Oro\Bundle\IntegrationBundle\Provider\SettingsProvider')
            ->disableOriginalConstructor()->getMock();
        $subscriberNS     = 'Oro\Bundle\IntegrationBundle\Form\EventListener';

        $channelSubscriber      = new ChannelFormSubscriber($registry, $settingsProvider);
        $ownerSubscriber        = $this->getMockBuilder($subscriberNS . '\DefaultUserOwnerSubscriber')
            ->setMethods(['postSet'])
            ->disableOriginalConstructor()
            ->getMock();
        $organizationSubscriber = $this->getMockBuilder($subscriberNS . '\OrganizationSubscriber')
            ->setMethods(['postSet'])
            ->disableOriginalConstructor()
            ->getMock();

        return new ChannelType(
            $ownerSubscriber,
            $channelSubscriber,
            $organizationSubscriber
        );
    }
}
