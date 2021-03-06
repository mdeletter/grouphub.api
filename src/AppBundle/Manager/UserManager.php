<?php

namespace AppBundle\Manager;

use AppBundle\Entity\User;
use AppBundle\Entity\UserExtraAttribute;
use AppBundle\Event\UserEvent;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class UserManager
 */
class UserManager
{
    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @param Registry $doctrine
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(Registry $doctrine, EventDispatcherInterface $dispatcher)
    {
        $this->doctrine = $doctrine;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param int $id
     *
     * @return User
     */
    public function findUser($id)
    {
        return $this->doctrine->getRepository('AppBundle:User')->find($id);
    }

    /**
     * @param string $query
     * @param string $sort
     * @param int    $offset
     * @param int    $limit
     * @param string $reference
     * @param string $loginName
     *
     * @return User|User[]
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function findUsers(
        $query = null,
        $sort = 'reference',
        $offset = 0,
        $limit = 100,
        $reference = null,
        $loginName = null
    ) {
        /** @var QueryBuilder $qb */
        $qb = $this->doctrine->getRepository('AppBundle:User')->createQueryBuilder('u');

        $qb->leftJoin(UserExtraAttribute::class, 'a', Expr\Join::LEFT_JOIN, 'a.user = u.id');

        if ($sort === 'name') {
            $sort = new Expr\OrderBy('u.lastName');
            $sort->add('u.firstName');
        } else {
            $sort = new Expr\OrderBy('u.' . $sort);
        }

        $qb->where('u.type = \'ldap\'')->orderBy($sort)->setFirstResult($offset)->setMaxResults($limit);

        if ($reference !== null) {
            $qb->andWhere('u.reference = :reference')->setParameter('reference', $reference);
        }

        if ($loginName !== null) {
            $qb->andWhere('u.loginName = :loginName')->setParameter('loginName', $loginName);
        }

        if (!empty($query)) {
            $terms = explode(' ', $query);

            if (!empty($terms)) {
                $qb->setParameter('annotation_type_email', 'email');
            }

            foreach ($terms as $i => $term) {
                $qb->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->like('u.firstName', ':term' . $i),
                        $qb->expr()->like('u.lastName', ':term' . $i),
                        $qb->expr()->like('u.loginName', ':term' . $i),
                        $qb->expr()->andX(
                            $qb->expr()->like('a.attribute', ':annotation_type_email'),
                            $qb->expr()->like('a.value', ':term' . $i)
                        )
                    )
                );

                $qb->setParameter('term' . $i, '%' . $term . '%');
            }
        }

        $query = $qb->getQuery();

        $paginator = new Paginator($query);

        $result = [
            'count' => $paginator->count(),
            'items' => $paginator->getIterator()->getArrayCopy(),
        ];

        if ((!empty($loginName) || !empty($reference)) && $result['count'] === 1) {
            $result = current($result['items']);
        }

        return $result;
    }

    /**
     * @param User $user
     */
    public function addUser(User $user)
    {
        $user->setTimeStamp(new \DateTime());

        $em = $this->doctrine->getManager();
        $em->persist($user);
        $em->flush();

        $this->dispatcher->dispatch('app.event.user.add', new UserEvent($user));
    }

    /**
     * @param User $user
     */
    public function updateUser(User $user)
    {
        $this->doctrine->getManager()->flush();

        $this->dispatcher->dispatch('app.event.user.update', new UserEvent($user));
    }

    /**
     * @param User $user
     */
    public function deleteUser(User $user)
    {
        $ownedGroups = $this->doctrine->getRepository('AppBundle:UserGroup')->findBy(['owner' => $user]);
        foreach ($ownedGroups as $group) {
            $group->setActive(0);
            $group->setOwner(
                $this->doctrine->getRepository('AppBundle:User')->findOneBy(['reference' => User::REFERENCE_TRASH])
            );
        }

        $this->dispatcher->dispatch('app.event.user.delete', new UserEvent($user));

        $em = $this->doctrine->getManager();
        $em->remove($user);
        $em->flush();
    }
}
