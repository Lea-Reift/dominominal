<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volante de Pago</title>
    <style>
        /* Email-safe reset and base styles */
        body,
        table,
        td,
        th,
        p,
        div,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            font-size: 14px;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 20px 0;
        }

        /* Gmail/Outlook anti-collapse techniques */
        .gmail-fix {
            min-width: 600px;
        }

        /* Prevent Gmail from hiding content */
        .prevent-collapse {
            mso-line-height-rule: exactly;
            line-height: 100%;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* App Colors from Filament */
        .primary-bg {
            background-color: #2563eb;
        }

        .slate-bg {
            background-color: #64748b;
        }

        .emerald-bg {
            background-color: #10b981;
        }

        .emerald-light-bg {
            background-color: #d1fae5;
        }

        .red-bg {
            background-color: #ef4444;
        }

        .red-light-bg {
            background-color: #fee2e2;
        }

        .amber-bg {
            background-color: #f59e0b;
        }

        .sky-bg {
            background-color: #0ea5e9;
        }

        .text-white {
            color: #ffffff;
        }

        .text-slate {
            color: #475569;
        }

        .text-emerald {
            color: #10b981;
        }

        .text-red {
            color: #ef4444;
        }

        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0 10px;
                max-width: none;
            }

            .voucher-table {
                font-size: 12px;
            }

            .mobile-stack {
                display: block !important;
                width: 100% !important;
            }
        }
    </style>
</head>

<body>
    {{-- Gmail preview protection --}}
    <div
        style="display: none; max-height: 0; overflow: hidden; opacity: 0; font-size: 1px; line-height: 1px; color: #f8fafc;">
        Información confidencial de nómina - Solo visible cuando se abre el correo -
        Comprobante Manual - {{ $voucher->name }} -
        {{ $voucher->period }} - Detalles financieros privados
        &#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;
    </div>

    <div class="email-wrapper gmail-fix">
        <div class="email-container">
            <!-- Header -->
            <div
                style="background: #1d4ed8; padding: 30px 20px; text-align: center;">
                <h1
                    style="color: #ffffff; font-size: 24px; font-weight: 700; margin: 0; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                    🏢 {{ $voucher->companyName }}
                </h1>
            </div>

            <!-- Greeting -->
            <div style="padding: 30px 20px 20px 20px; background-color: #ffffff;">
                <div
                    style="background-color: #f1f5f9; border-left: 4px solid #2563eb; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
                    <p style="margin: 0; font-size: 16px; color: #1e293b;">
                        <strong style="color: #2563eb;">Saludos
                            {{ explode(' ', $voucher->name)[0] }},</strong>
                    </p>
                    <p style="margin: 8px 0 0 0; color: #64748b; font-size: 14px;">
                        Su volante de pago de nómina está disponible para revisión.
                    </p>
                    <!-- Anti-preview barrier -->
                    <span
                        style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 0; line-height: 0; opacity: 0;"><!-- confidential data barrier --></span>
                </div>

                <!-- Content separator to prevent Gmail grouping -->
                <div style="margin: 20px 0; height: 1px; background: transparent;"></div>
                <!-- Force unique content to prevent Gmail grouping -->
                <div style="margin: 0; padding: 0; height: 0; overflow: hidden; font-size: 0; line-height: 0;"></div>

                <!-- Financial Information Section -->
                <div class="prevent-collapse" style="min-height: 50px; margin-top: 10px;">
                    <!-- Anti-Gmail-collapse wrapper -->
                    <div style="background-color: #ffffff; padding: 1px; margin: 1px 0;"></div>

                    @include('components.manual-payment-voucher-table', ['detail' => $voucher, 'mode' => 'mail'])
                </div>
            </div>

            <!-- Footer -->
            <div style="background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 12px;">
                    Este correo se envió automáticamente desde nuestro sistema de nómina.
                </p>
                <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 12px;">
                    Por favor, no responda directamente a este mensaje.
                </p>
                <hr
                    style="border: none; height: 1px; background: linear-gradient(to right, transparent, #e2e8f0, transparent); margin: 12px 0;">
                <p style="margin: 0; color: #64748b; font-size: 11px;">
                    © 2025 {{ $voucher->companyName }} • Sistema de Gestión de Nómina
                </p>
            </div>
        </div>
    </div>
</body>

</html>
