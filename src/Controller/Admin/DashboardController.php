<?php

namespace App\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Replaces admin-portal.php.
 *
 * The old file did everything in one place: access check, form handling,
 * database queries and HTML. In Symfony that is split up:
 *   - this controller: access check, tab selection, database queries
 *   - templates/admin/*.twig: the HTML
 *   - public/assets/css/admin-portal.css: the stylesheet
 */
#[Route('/admin')]
class DashboardController extends AbstractController
{
    // Same tabs as your old $admin_tabs array.
    private const TABS = [
        'overview'      => 'Overview',
        'site_controls' => 'Site Controls',
        'users'         => 'Users',
        'support'       => 'Customer Support Chat',
        'orders'        => 'Orders',
        'tickets'       => 'Tickets',
        'products'      => 'Products',
        'crud_log'      => 'CRUD Log',
    ];

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(Request $request, Connection $db): Response
    {
        // Access control (same keys your old site used).
        // TODO later: replace with Symfony Security + #[IsGranted('ROLE_ADMIN')]
        $session = $request->getSession();
        if (!$session->get('u_id') || $session->get('user_role') !== 'admin') {
            return $this->redirect('/');
        }

        $activeTab = $request->query->getString('tab', 'overview');
        if (!array_key_exists($activeTab, self::TABS)) {
            $activeTab = 'overview';
        }

        $data = [];

        if ($activeTab === 'overview') {
            $data = $this->overviewData($db);
        }

        // Other tabs will get their own "if ($activeTab === ...)" block here
        // as we port them over from admin-portal.php.

        return $this->render('admin/dashboard/index.html.twig', [
            'page_title'  => 'Timosa Tech - Admin Dashboard',
            'tabs'        => self::TABS,
            'active_tab'  => $activeTab,
            'user_name'   => $session->get('username', 'Admin'),

            // Read by templates/partials/header.html.twig
            'current_page' => 'admin',
            'is_logged_in' => true,
            'is_admin'     => true,
            // 'notif_role' / 'notif_unread' get added when notifications are ported
            'data'        => $data,
        ]);
    }

    /**
     * Same queries as the "Fetch data for Metrics tab" block in the old file.
     * $db->fetchOne() is the Symfony/Doctrine version of $pdo->query(...)->fetchColumn().
     */
    private function overviewData(Connection $db): array
    {
        $totalRevenue = (float) ($db->fetchOne(
            "SELECT SUM(total_amount) FROM orders WHERE status = 'completed'"
        ) ?: 0);

        // Everything still "in flight": not completed and not cancelled.
        $potentialRevenue = (float) ($db->fetchOne(
            "SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('completed', 'cancelled')"
        ) ?: 0);

        return [
            'total_users'       => (int) $db->fetchOne('SELECT COUNT(*) FROM users'),
            'total_products'    => (int) $db->fetchOne('SELECT COUNT(*) FROM products'),
            'total_orders'      => (int) $db->fetchOne('SELECT COUNT(*) FROM orders'),
            'total_revenue'     => $totalRevenue,
            'potential_revenue' => $potentialRevenue,
            'shop_revenue'      => $totalRevenue, // mirrors total revenue for now, like the old file
        ];
    }

    /**
     * DEV ONLY: lets you open the dashboard without the login page being
     * ported yet. Visit /admin/dev-login once, then go to /admin.
     * It does nothing outside the "dev" environment.
     */
    #[Route('/dev-login', name: 'admin_dev_login')]
    public function devLogin(
        Request $request,
        #[Autowire('%kernel.environment%')] string $env,
    ): Response {
        if ($env !== 'dev') {
            throw $this->createNotFoundException();
        }

        $session = $request->getSession();
        $session->set('u_id', 1);
        $session->set('user_role', 'admin');
        $session->set('username', 'Dev Admin');

        return $this->redirectToRoute('admin_dashboard');
    }
}