<?php


namespace ChronopostHomeDelivery\EventListeners;


use ChronopostHomeDelivery\ChronopostHomeDelivery;
use ChronopostHomeDelivery\Config\ChronopostHomeDeliveryConst;
use OpenApi\Events\DeliveryModuleOptionEvent;
use OpenApi\Events\OpenApiEvents;
use OpenApi\Model\Api\DeliveryModuleOption;
use OpenApi\Model\Api\ModelFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryArea;
use Thelia\Model\PickupLocation;
use Thelia\Module\Exception\DeliveryException;

class APIListener implements EventSubscriberInterface
{
    protected $modelFactory;
    protected $requestStack;

    public function __construct(ModelFactory $modelFactory = null, RequestStack $requestStack)
    {
        $this->modelFactory = $modelFactory;
        $this->requestStack = $requestStack;
    }

    /**
     * Get the list of delivery types
     *
     * @param DeliveryModuleOptionEvent $deliveryModuleOptionEvent
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getDeliveryModuleOptions(DeliveryModuleOptionEvent $deliveryModuleOptionEvent)
    {
        if ($deliveryModuleOptionEvent->getModule()->getId() !== ChronopostHomeDelivery::getModuleId()) {
            return ;
        }

        $activatedDeliveryTypes = ChronopostHomeDelivery::getActivatedDeliveryTypes();

        foreach (ChronopostHomeDeliveryConst::CHRONOPOST_HOME_DELIVERY_DELIVERY_CODES as $name => $code) {
            if (!in_array($code, $activatedDeliveryTypes, false)) {
                continue ;
            }

            $isValid = true;
            $orderPostage = null;

            try {
                $module = new ChronopostHomeDelivery();
                $country = $deliveryModuleOptionEvent->getCountry();

                $orderPostage = $module->getMinPostage(
                    $country,
                    $deliveryModuleOptionEvent->getCart()->getWeight(),
                    $deliveryModuleOptionEvent->getCart()->getTaxedAmount($country),
                    $code,
                    $this->requestStack->getCurrentRequest()->getSession()->getLang()->getLocale()
                );

                if (null === $orderPostage) {
                    $isValid = false;
                }

            } catch (\Exception $exception) {
                $isValid = false;
            }

            $minimumDeliveryDate = ''; // TODO (with a const array code => timeToDeliver to calculate delivery date from day of order)
            $maximumDeliveryDate = ''; // TODO (with a const array code => timeToDeliver to calculate delivery date from day of order)

            /** @var DeliveryModuleOption $deliveryModuleOption */
            $deliveryModuleOption = $this->modelFactory->buildModel('DeliveryModuleOption');
            $deliveryModuleOption
                ->setCode($code)
                ->setValid($isValid)
                ->setTitle($name)
                ->setImage('')
                ->setMinimumDeliveryDate($minimumDeliveryDate)
                ->setMaximumDeliveryDate($maximumDeliveryDate)
                ->setPostage(($orderPostage) ? $orderPostage->getAmount() : 0)
                ->setPostageTax(($orderPostage) ? $orderPostage->getAmountTax() : 0)
                ->setPostageUntaxed(($orderPostage) ? $orderPostage->getAmount() - $orderPostage->getAmountTax() : 0)
            ;

            $deliveryModuleOptionEvent->appendDeliveryModuleOptions($deliveryModuleOption);
        }
    }

    public static function getSubscribedEvents()
    {
        $listenedEvents = [];

        /** Check for old versions of Thelia where the events used by the API didn't exists */
        if (class_exists(DeliveryModuleOptionEvent::class)) {
            $listenedEvents[OpenApiEvents::MODULE_DELIVERY_GET_OPTIONS] = array("getDeliveryModuleOptions", 131);
        }

        return $listenedEvents;
    }
}
