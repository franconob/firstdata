<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/2/14
 * Time: 1:09 PM
 */

namespace FData\SecurityBundle\User;


use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @var Router
     */
    private $router;

    private static $roles = [
        "NEW_TRANSACTION",
        "REFUND",
        "TAGGED_VOID",
        "RECIBIR_MAIL",
        "PRE_AUTH",
        "EXPORT_CSV",
        "TAGGED_REFUND",
        "TAGGED_PRE_AUTH_COMP",
        "SOLO_OP_DIA",
        "OPERA_HOTEL"

    ];

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param \Symfony\Bundle\FrameworkBundle\Routing\Router $router
     */
    public function __construct(Connection $connection, Router $router)
    {
        $this->connection = $connection;
        $this->router     = $router;
    }

    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return UserInterface
     *
     * @see UsernameNotFoundException
     *
     * @throws UsernameNotFoundException if the user is not found
     *
     */
    public function loadUserByUsername($username)
    {
        if (false !== strpos($this->router->getContext()->getHost(), 'extranet')) {
            $user = $this->connection->fetchAssoc(
                "Select vtiger_contactdetails.contactid as contactid , vtiger_account.accountname as HOTEL ,
                vtiger_account.website as web, vtiger_account.phone as telefono,
                CONCAT(vtiger_attachments.path,vtiger_attachments.attachmentsid,'_',vtiger_attachments.name) as logo,
                vtiger_accountbillads.bill_street as dir_calle, vtiger_accountbillads.bill_pobox as dir_pobox,
                vtiger_accountbillads.bill_city as dir_ciudad, vtiger_accountbillads.bill_state as dir_provincia,
                vtiger_accountbillads.bill_country as dir_pais, vtiger_accountbillads.bill_code as dir_code,
                CONCAT_WS(' ',vtiger_contactdetails.firstname, vtiger_contactdetails.lastname) as nombre,
                vtiger_accountscf.cf_1240 as leyenda_recibo,
cf_1217 as 'NEW_TRANSACTION',
cf_1219 as 'REFUND',
cf_1221 as 'TAGGED_VOID',
cf_1223 as 'RECIBIR_MAIL',
cf_1218 as 'PRE_AUTH',
cf_1220 as 'EXPORT_CSV',
cf_1222 as 'TAGGED_REFUND',
cf_1232 as 'TAGGED_PRE_AUTH_COMP',
cf_1238 as 'SOLO_OP_DIA',
cf_1239 as 'OPERA_HOTEL',
cf_851 as password
from vtiger_contactdetails
inner join vtiger_crmentity
on vtiger_crmentity.crmid=vtiger_contactdetails.contactid
inner join vtiger_contactscf on  (vtiger_contactdetails.contactid= vtiger_contactscf.contactid)
inner join vtiger_account on (vtiger_contactdetails.accountid=vtiger_account.accountid)
INNER JOIN vtiger_accountbillads ON (vtiger_accountbillads.accountaddressid = vtiger_account.accountid)
INNER JOIN vtiger_accountscf ON (vtiger_accountscf.accountid = vtiger_account.accountid)
INNER JOIN vtiger_crmentity crm_ac ON (crm_ac.crmid = vtiger_account.accountid and crm_ac.deleted=0)
LEFT JOIN vtiger_senotesrel ON (vtiger_senotesrel.crmid = crm_ac.crmid)
LEFT JOIN vtiger_notes ON (vtiger_notes.notesid = vtiger_senotesrel.notesid and vtiger_notes.title = 'logo')
LEFT JOIN vtiger_attachments ON (vtiger_attachments.attachmentsid = vtiger_notes.notesid+1)
where vtiger_crmentity.deleted<>1 and email= ?
                "
                , array($username));

            if (!$user) {
                throw new UsernameNotFoundException(sprintf("El usuario %s no existe", $username));
            }

            $roles = ['ROLE_CONTACTO'];
            foreach (self::$roles as $role) {
                if (isset($user[$role]) && $user[$role] !== '0')
                    $roles[] = 'ROLE_' . $role;
            }

            $extra_data = [
                'dir_calle'     => $user['dir_calle'],
                'dir_pobox'     => $user['dir_pobox'],
                'dir_ciudad'    => $user['dir_ciudad'],
                'dir_provincia' => $user['dir_provincia'],
                'dir_pais'      => $user['dir_pais'],
                'dir_code'      => $user['dir_code'],
                'web'           => $user['web'],
                'telefono'      => $user['telefono'],
                'logo'          => $user['logo'],
                'leyenda_recibo' => $user['leyenda_recibo']
            ];

            return new User($user['contactid'], $username, $user['password'], "", $user['nombre'], $user['HOTEL'], $roles, $extra_data);

        } else {
            $user = $this->connection->fetchAssoc(
                "SELECT * from vtiger_users WHERE user_name = ?"
                , array($username));
            if (!$user) {
                throw new UsernameNotFoundException(sprintf("El usuario %s no existe", $username));
            }

            $vrol = $this->connection->fetchAll("select r.* from vtiger_role r
inner join vtiger_user2role ur on (r.roleid = ur.roleid) where
ur.userid = ?;", array($user['id']));

            if (!isset($vrol[0])) {

            } else {
                $roleid = $vrol[0]['roleid'];
                // Traigo los hijos del rol para poder ver sus cuentas
                $stmt = $this->connection->prepare('select * from vtiger_role r where r.parentrole like :rolel and r.roleid <> :role;');
                $stmt->bindValue(":rolel", "%" . $roleid . "%");
                $stmt->bindValue(":role", $roleid);

                $stmt->execute();
                $parentRoles = $stmt->fetchAll();

                $child_roles = [];

                foreach ($parentRoles as $parentRole) {
                    $_roles        = explode('::', $parentRole['parentrole']);
                    $pos           = array_search($roleid, $_roles);
                    $child_roles[] = array_slice($_roles, $pos + 1);
                }

                $childRoles = [];
                foreach ($child_roles as $cr) {
                    foreach ($cr as $r) {
                        if (!in_array($r, $childRoles)) {
                            $childRoles[] = $r;
                        }
                    }

                }


                $userids = [$user['id']];
                if (!empty($childRoles)) {
                    $stmt = $this->connection->executeQuery('
                    select id from vtiger_users u inner join vtiger_user2role ur on (u.id = ur.userid)
where ur.roleid IN (?)', array($childRoles), array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY));

                    $tmp_users = $stmt->fetchAll();
                    if ($tmp_users) {
                        foreach ($tmp_users as $_user) {
                            $userids[] = $_user['id'];
                        }
                    }
                }

            }

            $_hoteles = $this->connection->executeQuery("
Select vtiger_account.accountname as HOTEL from vtiger_account inner join vtiger_crmentity
on vtiger_crmentity.crmid=vtiger_account.accountid
INNER JOIN vtiger_accountscf ON (vtiger_accountscf.accountid = vtiger_account.accountid)
where vtiger_crmentity.deleted<>1 and smownerid IN (?)", array($userids), array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY));

            $hoteles = [];
            foreach ($_hoteles as $hotel) {
                $hoteles[] = $hotel['HOTEL'];
            }


            return new User($user['id'], $username, $user['user_hash'], "", $user['first_name'] . ' ' . $user['last_name'], $hoteles, array('ROLE_USUARIO', 'ROLE_CONCILIAR'));
        }

    }

    /**
     * Refreshes the user for the account interface.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     * @param UserInterface $user
     *
     * @return UserInterface
     *
     * @throws UnsupportedUserException if the account is not supported
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    /**
     * Whether this provider supports the given user class
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        return $class === "FData\SecurityBundle\User\User";
    }

    public function getUsernameForApiKey($apiKey)
    {
        return $this->connection->executeQuery("SELECT user_name from vtiger_users where accesskey = ?", array(
            $apiKey
        ))->fetchColumn(0);
    }
}