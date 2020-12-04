<?php

namespace App\Controller\Admin;

use App\Entity\Comment;
use App\Entity\CommentPage;
use App\Form\Admin\CommentType;
use App\Services\CommentService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
#[\Symfony\Component\Routing\Annotation\Route('/{_locale}/comments', name: 'admin_comment_', host: '%admin_host%', requirements: ['_locale' => 'en|fr|de'])]
class CommentController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var PaginatorInterface
     */
    private $paginator;
    /**
     * @var CommentService
     */
    private $commentService;
    public function __construct(\Symfony\Contracts\Translation\TranslatorInterface $translator, \Knp\Component\Pager\PaginatorInterface $paginator, \App\Services\CommentService $commentService)
    {
        $this->translator = $translator;
        $this->paginator = $paginator;
        $this->commentService = $commentService;
    }
    #[Route('/list', name: 'list')]
    public function list(\Symfony\Component\HttpFoundation\Request $request)
    {
        $form = $this->createSearchForm();
        $form->handleRequest($request);
        $query = $this->commentService->getQueryForSearch($form->getData());
        $entities = $this->paginator->paginate($query, $request->query->get('page', 1), 20);
        return $this->render('admin/comment/index.html.twig', ['entities' => $entities, 'search_form' => $form->createView()]);
    }
    private function createSearchForm()
    {
        $data = [];
        return $this->createFormBuilder($data)->setAction($this->generateUrl('admin_comment_list'))->setMethod('GET')->add('id', \Symfony\Component\Form\Extension\Core\Type\TextType::class, ['required' => false, 'label' => 'Id'])->add('submit', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, ['label' => 'Search'])->getForm();
    }
    private function manage(\Symfony\Component\HttpFoundation\Request $request, \App\Entity\Comment $entity)
    {
        $mode = $entity->getId() ? 'edit' : 'create';
        $form = $this->createForm(\App\Form\Admin\CommentType::class, $entity, ['locale' => $request->getLocale()]);
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $this->commentService->save($entity);
                // Launch the message flash
                $this->get('session')->getFlashBag()->add('notice', $this->translator->trans('notice.form.updated'));
                return $this->redirect($this->generateUrl('admin_comment_edit', ['id' => $entity->getId()]));
            }
            $this->get('session')->getFlashBag()->add('error', $this->translator->trans('notice.form.error'));
        }
        return $this->render('admin/comment/' . $mode . '.html.twig', ['form' => $form->createView(), 'entity' => $entity]);
    }
    #[Route('/create', name: 'create')]
    public function create(\Symfony\Component\HttpFoundation\Request $request)
    {
        $entity = new \App\Entity\CommentPage();
        $entity->setCreated(new \DateTime());
        return $this->manage($request, $entity);
    }
    #[Route('/edit/{id}', name: 'edit')]
    public function edit(\Symfony\Component\HttpFoundation\Request $request, $id)
    {
        $entity = $this->commentService->findOneToEdit($id);
        if (!$entity) {
            throw $this->createNotFoundException('Comment not found');
        }
        $entity->setUpdated(new \DateTime());
        return $this->manage($request, $entity);
    }
    #[Route('/delete/{id}', name: 'delete')]
    public function delete(\Symfony\Component\HttpFoundation\Request $request, $id)
    {
        /** @var Comment $comment */
        $comment = $this->commentService->findOneToEdit($id);
        if (!$comment) {
            throw $this->createNotFoundException('Comment not found');
        }
        $this->commentService->remove($comment);
        // Launch the message flash
        $this->get('session')->getFlashBag()->add('notice', $this->translator->trans('notice.form.deleted'));
        return $this->redirect($request->headers->get('referer'));
    }
}
