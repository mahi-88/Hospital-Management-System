<?php

namespace Leantime\Domain\TwoFA\Controllers;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Leantime\Core\Controller\Controller;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\TwoFactorAuthException;
use Symfony\Component\HttpFoundation\Response;

class Edit extends Controller
{
    private UserRepository $userRepo;

    public function init(UserRepository $userRepo): void
    {
        $this->userRepo = $userRepo;
    }

    /**
     * @throws TwoFactorAuthException
     */
    public function run(): Response
    {
        $userId = session('userdata.id');

        $user = $this->userRepo->getUser($userId);

        $tfa = new TwoFactorAuth('PixelForge Nexus', 6, 30, 'sha1', new class implements IQRCodeProvider
        {
            public function getMimeType(): string
            {
                return 'image/png';
            }

            public function getQRCodeImage($qrtext, $size): string
            {
                $writer = new PngWriter;

                $qrCode = new QrCode(data: $qrtext, size: $size, backgroundColor: new Color(255, 255, 255, 127));

                $label = new Label(
                    text: 'Label',
                    textColor: new Color(255, 0, 0)
                );

                return $writer->write($qrCode, null, null)->getString();
            }
        });
        $secret = $user['twoFASecret'];

        if (isset($_POST['disable'])) {
            if (isset($_POST[session('formTokenName')]) && $_POST[session('formTokenName')] == session('formTokenValue')) {
                $this->userRepo->patchUser($userId, [
                    'twoFAEnabled' => 0,
                    'twoFASecret' => null,
                ]);

                $user['twoFASecret'] = null;
                $user['twoFAEnabled'] = 0;
                $secret = null;

                $this->tpl->assign('twoFAEnabled', false);
            } else {
                $this->tpl->setNotification($this->language->__('notification.form_token_incorrect'), 'error');
            }
        }

        if (empty($secret)) {
            $secret = $tfa->createSecret(160);
        }

        $this->tpl->assign('secret', $secret);

        if (isset($_POST['save'])) {
            if (isset($_POST['secret'])) {
                $secret = $_POST['secret'];
                $this->userRepo->patchUser($userId, [
                    'twoFASecret' => $secret,
                ]);

                $user['twoFASecret'] = $secret;
                $this->tpl->assign('secret', $secret);
            }

            if (isset($_POST['twoFACode']) && isset($secret)) {
                $verified = $tfa->verifyCode($secret, $_POST['twoFACode']);
                if ($verified) {
                    $this->userRepo->patchUser($userId, [
                        'twoFAEnabled' => 1,
                        'twoFASecret' => $secret,
                    ]);
                    $user['twoFAEnabled'] = 1;
                    $this->tpl->setNotification($this->language->__('notification.twoFA_enabled_success'), 'success', 'twoFAenabled');
                    $this->tpl->assign('twoFAEnabled', true);
                } else {
                    $this->tpl->setNotification($this->language->__('notification.incorrect_twoFA_code'), 'error');
                }
            }
        }

        if ($user['twoFAEnabled']) {
            $this->tpl->assign('twoFAEnabled', true);
        } else {
            $qrData = $tfa->getQRCodeImageAsDataUri($user['username'], $secret);
            $this->tpl->assign('qrData', $qrData);
            $this->tpl->assign('twoFAEnabled', false);
        }

        // Sensitive Form, generate form tokens
        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        session(['formTokenName' => substr(str_shuffle($permitted_chars), 0, 32)]);
        session(['formTokenValue' => substr(str_shuffle($permitted_chars), 0, 32)]);

        return $this->tpl->display('twofa.edit');
    }
}
