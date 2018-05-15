<?php
/**
 */

namespace Commercetools\Symfony\ExampleBundle\Controller;

use Commercetools\Core\Client;
use Commercetools\Core\Model\Common\Address;
use Commercetools\Core\Request\Customers\CustomerByIdGetRequest;
use Commercetools\Symfony\CtpBundle\Entity\UserAddress;
use Commercetools\Symfony\CtpBundle\Entity\UserDetails;
use Commercetools\Symfony\ExampleBundle\Model\Form\Type\AddressType;
use Commercetools\Symfony\ExampleBundle\Model\Form\Type\UserType;
use Commercetools\Symfony\CtpBundle\Model\Repository\CartRepository;
use Commercetools\Symfony\CtpBundle\Security\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;


class UserController extends Controller
{
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

    public function detailsAction(Request $request)
    {
        /**
         * @var User $user
         */
        $customerId = $this->get('security.token_storage')->getToken()->getUser()->getId();
        $customer = $this->get('commercetools.repository.customer')->getCustomer($request->getLocale(), $customerId);
        $entity = UserDetails::ofCustomer($customer);

        $form = $this->createForm(UserType::class, $entity)
            ->add('submit', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isValid() && $form->isSubmitted()){

            $firstName = $form->get('firstName')->getData();
            $lastName = $form->get('lastName')->getData();
            $email = $form->get('email')->getData();
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            $customer = $this->get('commercetools.repository.customer')
                ->setCustomerDetails($request->getLocale(), $customer, $firstName, $lastName, $email);

            if (is_null($customer)){
                $this->addFlash('error', 'Error updating user!');
                return $this->redirect($this->generateUrl('_ctp_example_user_details'));
            }else{
                $this->addFlash('notice', 'User updated');
            }

            if (isset($newPassword)){
                try{
                    $this->get('commercetools.repository.customer')
                        ->setNewPassword($request->getLocale(), $customer, $currentPassword, $newPassword);
                } catch (\InvalidArgumentException $e){
                    $this->addFlash('error', $this->get($e->getMessage(), [] , 'customers'));
                    dump($e->getMessage());
                    return new Response($e->getMessage());
                }
            }

        }

        return $this->render('ExampleBundle:User:user.html.twig',
            [
                'formDetails' => $form->createView()
            ]
        );
    }

    public function addressBookAction(Request $request)
    {
        /**
         * @var User $user
         */
        $customerId = $this->get('security.token_storage')->getToken()->getUser()->getId();
        $customer = $this->get('commercetools.repository.customer')->getCustomer($request->getLocale(), $customerId);

        return $this->render('ExampleBundle:User:addressBook.html.twig',
            [
                'customer' => $customer
            ]
        );
    }

    public function editAddressAction(Request $request, $addressId)
    {
        /**
         * @var User $user
         */
        $customerId = $this->get('security.token_storage')->getToken()->getUser()->getId();
        $repository = $this->get('commercetools.repository.customer');
        $customer = $repository->getCustomer($request->getLocale(), $customerId);
        $address = $customer->getAddresses()->getById($addressId);

        $entity = UserAddress::ofAddress($address);

        $form = $this->createFormBuilder(['address' => $entity->toArray()])
            ->add('address', AddressType::class)
            ->add('Submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isValid() && $form->isSubmitted()){
            $address = Address::fromArray($form->get('address')->getData());

            $submit = $repository->setAddresses(
                $request->getLocale(),
                $customer,
                $address,
                $addressId
            );
        }

        return $this->render(
            'ExampleBundle:User:editAddress.html.twig',
            [
                'form_address' => $form->createView()
            ]
        );
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
