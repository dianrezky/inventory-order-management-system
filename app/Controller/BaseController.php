<?php

namespace App\Controller;

use App\Core\Container;
use App\Core\Result;
use App\Core\Response;
use App\Core\ViewModel;

// BR-017: the auth/role guards here are the only authorization; views never enforce it.
class BaseController
{
    protected $container;

    protected $currentUser;

    public function __construct($container)
    {
        $this->container = $container;
        $this->currentUser = $this->getAuthService()->currentUser();
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function currentUser()
    {
        return $this->currentUser;
    }

    protected function view($template, $data = [], $layout = 'layouts/main')
    {
        $viewModel = new ViewModel($template, $data, $layout);
        $viewModel->setVariable('currentUser', $this->currentUser());
        $viewModel->setVariable('csrfToken', $this->csrfToken());
        $viewModel->setVariable(
            'grantedPermissions',
            $this->currentUser !== null
                ? $this->getPermissionService()->grantedKeysForRole($this->currentUser->role->value)
                : [],
        );
        // So views can encode a raw db id into the same obfuscated token used in
        // {id} route segments (e.g. inside an href, encode(product->id))
        // inside an href) — never print $product->id etc. directly in a URL.
        $viewModel->setVariable('idObfuscator', $this->container->getIdObfuscator());

        return $viewModel;
    }

    // A form re-rendered with its errors is a failed request: 400 for a rule
    // violation (PROJECT_REFERENCE "Validasi gagal → 400"), 500 when the service failed
    protected function formErrorStatus($result)
    {
        if ($result->code === Result::CODE_INTERNAL) {
            return 500;
        }

        return 400;
    }

        protected function redirect($url, $statusCode = 302)
    {
        return Response::redirect($url, $statusCode);
    }

    protected function json($data, $statusCode = 200)
    {
        return Response::json($data, $statusCode);
    }

    protected function notFound($message = 'Page not found.')
    {
        return Response::notFound($message);
    }

    // Decodes an {id} route segment produced by IdObfuscator::encode(). Returns
    // null for a malformed/tampered token — callers must treat that exactly like
    // "not found" (return $this->notFound()), never as a valid/zero id.
    protected function decodeId($token)
    {
        return $this->container->getIdObfuscator()->decode((string) $token);
    }

    // Encodes a raw db id back into a token before putting it in a redirect URL
    // (e.g. after create/submit/approve). Controller-side counterpart of the
    // 'idObfuscator' view variable set in view() above.
    protected function encodeId($id)
    {
        return $this->container->getIdObfuscator()->encode((int) $id);
    }

    protected function forbidden($message = "You don't have permission to access this page.")
    {
        return Response::forbidden($message);
    }

    protected function badRequest($message = 'Bad request.')
    {
        return Response::badRequest($message);
    }

    protected function csv($content, $filename)
    {
        return Response::csv($content, $filename);
    }

    protected function csrfToken()
    {
        $session = $this->container->getSessionManager();
        $token = $session->get('_csrf_token');

        // Stable for the session's lifetime, not regenerated per-request, so multi-tab
        // use and back-button resubmits keep working; login issues a fresh one on auth.
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set('_csrf_token', $token);
        }

        return $token;
    }

    protected function requireCsrf()
    {
        // Defense-in-depth alongside SameSite=Lax cookies; called at the top of every
        // POST/state-changing action.
        $submitted = $_POST['_csrf_token'] ?? null;
        $expected = $this->container->getSessionManager()->get('_csrf_token');

        if (
            !is_string($submitted)
            || !is_string($expected)
            || $expected === ''
            || !hash_equals($expected, $submitted)
        ) {
            return Response::badRequest('Your session has expired or the form is invalid. Please try again.');
        }

        return null;
    }

    protected function requireAuth()
    {
        // AUTH-01 FR-1.8, BR-017: not automatic; every action behind auth calls this.
        if ($this->currentUser === null) {
            return $this->redirect('/login');
        }

        return null;
    }

    protected function requirePermission($key, $forbiddenMessage = null)
    {
        // BR-017: hiding the control in the view is not authorization; this check is.
        // The role-to-key mapping lives in role_permissions (see PermissionService),
        // not as literal Role comparisons here.
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        if (!$this->getPermissionService()->roleHasPermission($this->currentUser->role->value, $key)) {
            // A caller-specific reason (e.g. SOD-01's approve denial) replaces the generic 403 text
            if ($forbiddenMessage !== null) {
                $denied = $this->forbidden($this->t($forbiddenMessage));
            } else {
                $denied = $this->forbidden();
            }

            return $denied;
        }

        return null;
    }

    protected function requirePermissionWithCsrf($key, $forbiddenMessage = null)
    {
        $permissionError = $this->requirePermission($key, $forbiddenMessage);
        if ($permissionError !== null) {
            return $permissionError;
        }

        return $this->requireCsrf();
    }

    protected function requireAuthWithCsrf()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        return $this->requireCsrf();
    }

    protected function isXhr()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    // Reads one request value from POST only. Every page's filter/pagination/
    // sort/export controls submit via POST so no state ever lands in the
    // address bar; query-string params are deliberately ignored, so a typed
    // URL like /reports?q=x just renders the unfiltered page.
    protected function requestParam(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        return $default;
    }

    protected function searchTerm()
    {
        $search = trim((string) ($this->requestParam('q') ?? ''));

        return $search === '' ? null : $search;
    }

    // Reads a single discrete text filter field (e.g. sku, product_name,
    // email) — the per-column counterpart to searchTerm()'s single combined
    // "q" box, used by list pages whose filter panel exposes one field per
    // filterable attribute instead of one free-text search box.
    protected function textFilter(string $key): ?string
    {
        $value = trim((string) ($this->requestParam($key) ?? ''));

        return $value === '' ? null : $value;
    }

    // Reads the shared "status" filter (status[]=active&status[]=inactive) used by every
    // master-data list page's filter panel. Returns an array of 'active'/'inactive' values,
    // or null when none are selected.
    protected function statusFilter()
    {
        $raw = $this->requestParam('status');
        if ($raw === null) { return null; }
        if (is_array($raw)) {
            $valid = array_filter($raw, static fn ($v) => in_array($v, ['active', 'inactive'], true));
            return count($valid) > 0 ? array_values($valid) : null;
        }
        $s = trim((string) $raw);
        return in_array($s, ['active', 'inactive'], true) ? [$s] : null;
    }

    // Reads the "role" filter (role[]=Admin&role[]=Sales) on the Users list
    // page's filter panel. Returns an array of valid Role enum values.
    protected function roleFilter()
    {
        $raw = $this->requestParam('role');
        if ($raw === null) { return null; }
        if (is_array($raw)) {
            $valid = array_filter($raw, static fn ($v) => \App\Entity\Role::tryFrom((string) $v) !== null);
            return count($valid) > 0 ? array_values($valid) : null;
        }
        $r = trim((string) $raw);
        return \App\Entity\Role::tryFrom($r) !== null ? [$r] : null;
    }

    // Reads the "warehouse_id" filter (warehouse_id[]=1&warehouse_id[]=2). Returns an
    // array of positive int ids, or null when none are selected.
    protected function warehouseIdFilter()
    {
        $raw = $this->requestParam('warehouse_id');
        if ($raw === null) { return null; }
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $v) {
                $id = filter_var($v, FILTER_VALIDATE_INT);
                if ($id !== false && $id > 0) { $ids[] = $id; }
            }
        } else {
            $id = filter_var((string) $raw, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) { $ids[] = $id; }
        }
        return !empty($ids) ? $ids : null;
    }

    protected function parseItemsFromRequest($productIdKey, $qtyKey, $priceKey)
    {
        $productIds = $_POST[$productIdKey] ?? [];
        $qtys = $_POST[$qtyKey] ?? [];
        $prices = $_POST[$priceKey] ?? [];

        $items = [];
        $count = max(count($productIds), count($qtys), count($prices));

        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'product_id' => $productIds[$i] ?? null,
                'qty' => $qtys[$i] ?? null,
                'price' => $prices[$i] ?? null,
            ];
        }

        return $items;
    }

    // ================================================================
    // SERVICE GETTERS
    // ================================================================

    protected function getAuthService()
    {
        return $this->container->getAuthService();
    }

    protected function getSessionManager()
    {
        return $this->container->getSessionManager();
    }

    // One-shot value set before a redirect (replaces ?flag=1 query params,
    // which must never carry state in the URL). Read once, then removed.
    protected function pullFlash($key)
    {
        $session = $this->container->getSessionManager();
        $value = $session->get($key);
        $session->remove($key);

        return $value;
    }

    protected function getPermissionService()
    {
        return $this->container->getPermissionService();
    }

    // ================================================================
    // TRANSLATION METHODS
    // ================================================================

    protected static function messages()
    {
        return [];
    }

    protected function translate($key)
    {
        $messages = static::messages();
        $baseMessages = BaseController::messages();

        return $messages[$key] ?? $baseMessages[$key] ?? $key;
    }

    protected function t($key)
    {
        return $this->translate($key);
    }
}
