<?php
declare(strict_types=1);

namespace Commercetools\Symfony\ExampleBundle\Controller;

use Commercetools\Core\Client;
use Commercetools\Core\Model\Customer\CustomerReference;
use Commercetools\Core\Model\ShoppingList\ShoppingList;
use Commercetools\Core\Request\ShoppingLists\Command\ShoppingListAddLineItemAction;
use Commercetools\Core\Request\ShoppingLists\Command\ShoppingListChangeLineItemQuantityAction;
use Commercetools\Core\Request\ShoppingLists\Command\ShoppingListRemoveLineItemAction;
use Commercetools\Symfony\CtpBundle\Model\QueryParams;
use Commercetools\Symfony\ExampleBundle\Model\Form\Type\AddToShoppingListType;
use Commercetools\Symfony\ShoppingListBundle\Manager\ShoppingListManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;


class ShoppingListController extends Controller
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var ShoppingListManager
     */
    private $manager;

    /**
     * ShoppingListController constructor.
     */
    public function __construct(Client $client, ShoppingListManager $manager)
    {
        $this->client = $client;
        $this->manager = $manager;
    }

    public function indexAction(Request $request, UserInterface $user = null)
    {
        $params = new QueryParams();
        $params->add('expand', 'lineItems[*].variant');

        if(is_null($user)){
            $session = $this->get('session');
            $shoppingLists = $this->manager->getAllOfAnonymous($request->getLocale(), $session->getId(), $params);
        } else {
            $shoppingLists = $this->manager->getAllOfCustomer($request->getLocale(), CustomerReference::ofId($user->getId()), $params);
        }

        return $this->render('ExampleBundle:shoppinglist:index.html.twig', ['lists' => $shoppingLists]);
    }

    public function createAction(Request $request, UserInterface $user = null)
    {
        if(is_null($user)){
            $this->manager->createShoppingListByAnonymous($request->getLocale(), $this->get('session')->getId(), $request->get('_shoppingListName'));
        } else {
            $this->manager->createShoppingListByCustomer($request->getLocale(), CustomerReference::ofId($user->getId()), $request->get('_shoppingListName'));
        }

        return $this->redirectToRoute('_ctp_example_shoppingList');
    }

    public function deleteByIdAction(Request $request, UserInterface $user = null, $shoppingListId)
    {
        if(is_null($user)){
            $this->manager->deleteShoppingListByAnonymous($request->getLocale(), $this->get('session')->getId(), $shoppingListId);
        } else {
            $this->manager->deleteShoppingListByCustomer($request->getLocale(), CustomerReference::ofId($user->getId()), $shoppingListId);
        }

        return new RedirectResponse($this->generateUrl('_ctp_example_cart'));
    }

    public function addLineItemAction(Request $request, UserInterface $user = null)
    {
        $shoppingListsIds = [];

        if(is_null($user)){
            $shoppingLists = $this->manager->getAllOfAnonymous($request->getLocale(), $this->get('session')->getId());
        } else {
            $shoppingLists = $this->manager->getAllOfCustomer($request->getLocale(), CustomerReference::ofId($user->getId()));
        }

        foreach ($shoppingLists as $shoppingList) {
            /** @var ShoppingList $shoppingList */
            $shoppingListsIds[(string)$shoppingList->getName()] = $shoppingList->getId();
        }

        $data = [
            'variantIdText' => true,
            'shopping_lists' => $shoppingListsIds
        ];

        $form = $this->createForm(AddToShoppingListType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $shoppingListId = $form->get('_shoppingListId')->getData();

            if(!is_null($shoppingListId)){
                $shoppingList = $this->manager->getById($request->getLocale(), $shoppingListId);
                $updateBuilder = $this->manager->update($shoppingList);
                $updateBuilder->addLineItem(function (ShoppingListAddLineItemAction $action) use($form): ShoppingListAddLineItemAction {
                    $action->setProductId($form->get('_productId')->getData());
                    $action->setVariantId((int)$form->get('_variantId')->getData());
                    $action->setQuantity(1);
                    return $action;
                });

                $updateBuilder->flush();

            } else {
                $this->addFlash('error', 'Not valid shopping list provided');
            }
        }

        return $this->redirectToRoute('_ctp_example_shoppingList');
    }

    public function removeLineItemAction(Request $request)
    {
        $shoppingList = $this->manager->getById($request->getLocale(), $request->get('_shoppingListId'));
        $builder = $this->manager->update($shoppingList)
            ->addAction(ShoppingListRemoveLineItemAction::ofLineItemId($request->get('_lineItemId')));

        $builder->flush();

        return $this->redirectToRoute('_ctp_example_shoppingList');
    }

    public function changeLineItemQuantityAction(Request $request)
    {
        $shoppingList = $this->manager->getById($request->getLocale(), $request->get('_shoppingListId'));
        $builder = $this->manager->update($shoppingList)
            ->addAction(ShoppingListChangeLineItemQuantityAction::ofLineItemIdAndQuantity(
                $request->get('_lineItemId'), (int)$request->get('_lineItemQuantity')));

        $builder->flush();

        return $this->redirectToRoute('_ctp_example_shoppingList');
    }

}
