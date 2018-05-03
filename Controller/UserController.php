<?php
/**
 * @author: Ylambers <yaron.lambers@commercetools.de>
 */

namespace Commercetools\Symfony\ExampleBundle\Controller;

use Commercetools\Core\Client;
use Commercetools\Core\Model\Common\Address;
use Commercetools\Core\Model\Customer\Customer;
use Commercetools\Core\Request\Customers\Command\CustomerChangeAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerChangeEmailAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetFirstNameAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetLastNameAction;
use Commercetools\Core\Request\Customers\CustomerByIdGetRequest;
use Commercetools\Core\Request\Customers\CustomerPasswordChangeRequest;
use Commercetools\Symfony\CtpBundle\Entity\UserAddress;
use Commercetools\Symfony\CtpBundle\Entity\UserDetails;
use Commercetools\Symfony\ExampleBundle\Model\Form\Type\AddressType;
use Commercetools\Symfony\ExampleBundle\Model\Form\Type\UserType;
use Commercetools\Symfony\CustomerBundle\Manager\CustomerManager;
use Commercetools\Symfony\CtpBundle\Security\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Security\Core\User\UserInterface;


class UserController extends Controller
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var CustomerManager
     */
    private $manager;

    /**
     * CustomerController constructor.
     */
    public function __construct(Client $client, CustomerManager $manager)
    {
        $this->client = $client;
        $this->manager = $manager;
    }

    public function indexAction()
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        return $this->render('ExampleBundle:catalog:index.html.twig',
            [
                'user' => $user
            ]
        );
    }

    public function loginAction(Request $request)
    {
        $authenticationUtils = $this->get('security.authentication_utils');

        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('ExampleBundle:user:login.html.twig',
            [
                'last_username' => $lastUsername,
                'error' => $error
            ]
        );
    }

    public function detailsAction(Request $request, UserInterface $user)
    {
        $customer = $this->manager->getById($request->getLocale(), $user->getId());
        $entity = UserDetails::ofCustomer($customer);

        $form = $this->createForm(UserType::class, $entity)
            ->add('submit', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){

            $firstName = $form->get('firstName')->getData();
            $lastName = $form->get('lastName')->getData();
            $email = $form->get('email')->getData();
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            $customerBuilder = $this->manager->update($customer);
            $customerBuilder->setActions([
                CustomerSetFirstNameAction::of()->setFirstName($firstName),
                CustomerSetLastNameAction::of()->setLastName($lastName),
                CustomerChangeEmailAction::ofEmail($email)
            ]);

//            if (isset($newPassword)){
//                $customerBuilder->addAction(CustomerPasswordChangeRequest::ofIdVersionAndPasswords(
//                    $customer->getId(),
//                    $customer->getVersion(),
//                    $currentPassword,
//                    $newPassword
//                ));
//            }

            try{
                $customerBuilder->flush();
            }
            catch (\Exception $e){
                $this->addFlash('error', 'something wrong');
            }
        }

        return $this->render('ExampleBundle:User:user.html.twig',
            [
                'formDetails' => $form->createView()
            ]
        );
    }

    public function addressBookAction(Request $request, UserInterface $user)
    {
        $customer = $this->manager->getById($request->getLocale(), $user->getId());

        return $this->render('ExampleBundle:User:addressBook.html.twig',
            [
                'customer' => $customer
            ]
        );
    }

    public function editAddressAction(Request $request, UserInterface $user, $addressId)
    {
        $customer = $this->manager->getById($request->getLocale(), $user->getId());
        $address = $customer->getAddresses()->getById($addressId);

        $entity = UserAddress::ofAddress($address);

        $form = $this->createFormBuilder(['address' => $entity->toArray()])
            ->add('address', AddressType::class)
            ->add('Submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){
            $address = Address::fromArray($form->get('address')->getData());

            $customerBuilder = $this->manager->update($customer)
                ->setActions([CustomerChangeAddressAction::ofAddressIdAndAddress($addressId, $address)]);
            $customerBuilder->flush();
        }

        return $this->render(
            'ExampleBundle:User:editAddress.html.twig',
            [
                'form_address' => $form->createView()
            ]
        );
    }

    public function showOrdersAction(Request $request)
    {
        $orders = $this->get('commercetools.repository.order')->getOrders($request->getLocale(), $this->getUser()->getId());

        return $this->render('ExampleBundle:user:orders.html.twig', [
            'orders' => $orders
        ]);
    }

    public function showOrderAction(Request $request, $orderId)
    {
        $order = $this->get('commercetools.repository.order')->getOrder($request->getLocale(), $orderId);

        return $this->render('ExampleBundle:user:order.html.twig', [
            'order' => $order
         ]);
    }

    protected function getCustomer(User $user)
    {
        if (!$user instanceof User){
            throw new \InvalidArgumentException;
        }

        /**
         * @var Client $client
         */
        $client = $this->get('commercetools.client');

        $request = CustomerByIdGetRequest::ofId($user->getId());
        $response = $request->executeWithClient($client);

        $customer = $request->mapResponse($response);

        return $customer;
    }
}
