<?php

namespace App\Controllers;

use App\Services\ServiceException;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * @var list<string>
     */
    protected $helpers = ['url', 'form', 'pasabuy', 'ui'];

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);
    }

    protected function currentUserId(): int
    {
        return (int) session('user_id');
    }

    protected function isAdmin(): bool
    {
        return session('role') === 'admin';
    }

    /**
     * Runs a service call and turns the outcome into a redirect with a flash
     * message. Business-rule failures (ServiceException) are shown to the
     * user; anything unexpected is logged and replaced by a generic message.
     */
    protected function attempt(callable $action, string $success, string $redirectTo, bool $keepInput = false): RedirectResponse
    {
        try {
            $action();

            return redirect()->to($redirectTo)->with('success', $success);
        } catch (ServiceException $e) {
            $redirect = redirect()->to($redirectTo)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', '[{class}] {message} in {file}:{line}', [
                'class'   => static::class,
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            $redirect = redirect()->to($redirectTo)->with('error', 'Something went wrong. Nothing was changed. Please try again.');
        }

        return $keepInput ? $redirect->withInput() : $redirect;
    }

    /**
     * Redirect back with validation errors and the submitted input (except
     * secrets) so forms can be re-filled.
     *
     * @param array<string, string> $errors
     */
    protected function backWithErrors(array $errors, string $message = 'Please correct the highlighted fields.'): RedirectResponse
    {
        return redirect()->back()
            ->withInput()
            ->with('error', $message)
            ->with('errors', $errors);
    }
}
