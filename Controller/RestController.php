<?php

namespace Pim\Bundle\CustomEntityBundle\Controller;

use Pim\Bundle\CustomEntityBundle\Configuration\Registry;
use Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity;
use Pim\Bundle\CustomEntityBundle\Manager\ManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author    Anaël Chardan <anael.chardan@akeneo.com
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class RestController extends AbstractController
{
    /**
     * @var Registry
     */
    protected $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Return the list of registred references data
     *
     * @return JsonResponse
     */
    public function listAction()
    {
        $referenceDataNames = $this->registry->getNames();

        return new JsonResponse(array_combine($referenceDataNames, $referenceDataNames));
    }

    /**
     * Creates a custom entity
     *
     * @param Request $request
     * @param string  $customEntityName
     *
     * @return JsonResponse
     */
    public function createAction(Request $request, string $customEntityName): JsonResponse
    {
        $entity = $this->getManager($customEntityName)->create(
            $this->getEntityClass($customEntityName),
            $this->getDecodedContent($request->getContent())
        );

        $this->getManager($customEntityName)->save($entity);

        $responseContent = [
            'customEntityName' => $customEntityName,
            'id'               => $entity->getId(),
        ];

        return new JsonResponse($responseContent);
    }

    /**
     * Get a custom entity
     *
     * @param string $customEntityName
     * @param int    $id
     *
     * @return JsonResponse
     */
    public function getAction(string $customEntityName, int $id): JsonResponse
    {
        $entity = $this->findEntity($customEntityName, $id);
        $normalized = $this->normalize($customEntityName, $entity);

        return new JsonResponse($normalized);
    }

    /**
     * Removes a custom entity
     *
     * @param string $customEntityName
     * @param int    $id
     *
     * @return JsonResponse
     */
    public function removeAction(string $customEntityName, int $id): JsonResponse
    {
        $entity = $this->findEntity($customEntityName, $id);
        $this->getManager($customEntityName)->remove($entity);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Update and save a custom entity
     *
     * @param Request $request
     * @param string  $customEntityName
     * @param int     $id
     *
     * @return JsonResponse
     */
    public function updateAction(Request $request, string $customEntityName, int $id): JsonResponse
    {
        $data = $this->getDecodedContent($request->getContent());
        $entity = $this->findEntity($customEntityName, $id);
        $manager = $this->getManager($customEntityName);
        $manager->update($entity, $data);
        $manager->save($entity);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param string $customEntityName
     *
     * @return ManagerInterface
     */
    protected function getManager(string $customEntityName): ManagerInterface
    {
        $configuration = $this->registry->get($customEntityName);
        $managerRegistry = $this->container->get('pim_custom_entity.manager.registry');

        return $managerRegistry->getFromConfiguration($configuration);
    }

    /**
     * @param string $customEntityName
     *
     * @return string
     */
    protected function getEntityClass(string $customEntityName): string
    {
        $configuration = $this->registry->get($customEntityName);

        return $configuration->getEntityClass();
    }

    /**
     * @param string $customEntityName
     * @param int    $id
     *
     * @return AbstractCustomEntity
     */
    protected function findEntity(string $customEntityName, int $id): AbstractCustomEntity
    {
        $manager = $this->getManager($customEntityName);
        $entity = $manager->find($this->getEntityClass($customEntityName), $id);

        if (null === $entity) {
            throw new NotFoundHttpException(
                sprintf('Unable to find the entity "%s" with code "%d"', $customEntityName, $id)
            );
        }

        return $entity;
    }

    /**
     * Normalizes an entity into the internal array format
     *
     * @param string               $customEntityName
     * @param AbstractCustomEntity $entity
     *
     * @return array
     */
    protected function normalize(string $customEntityName, AbstractCustomEntity $entity): array
    {
        $manager = $this->getManager($customEntityName);
        $context = [
            'customEntityName' => $customEntityName,
            'form'             => sprintf('pim-%s-edit-form', $customEntityName),
        ];

        $normalized = $manager->normalize($entity, 'internal_api', $context);

        return $normalized;
    }

    /**
     * Get the JSON decoded content. If the content is not a valid JSON, it throws an error 400.
     *
     * @param string $content content of a request to decode
     *
     * @throws BadRequestHttpException
     *
     * @return array
     */
    protected function getDecodedContent($content)
    {
        $decodedContent = json_decode($content, true);

        if (null === $decodedContent) {
            throw new BadRequestHttpException('Invalid json payload received');
        }

        return $decodedContent;
    }
}
