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
        {{ $detail?->payroll?->company?->name ?? 'Empresa' }} - {{ $detail?->employee?->first_name ?? 'Empleado' }} -
        {{ $detail?->payroll?->period?->format('F Y') ?? 'Período' }} - Detalles financieros privados
        &#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;&#847;&zwnj;&nbsp;&#8199;&shy;
    </div>

    <div class="email-wrapper gmail-fix">
        <div class="email-container">
            <!-- Header -->
            <div
                style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 30px 20px; text-align: center;">
                <h1
                    style="color: #ffffff; font-size: 24px; font-weight: 700; margin: 0; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                    🏢 {{ $detail?->payroll?->company?->name ?? 'Empresa' }}
                </h1>
            </div>

            <!-- Greeting -->
            <div style="padding: 30px 20px 20px 20px; background-color: #ffffff;">
                <div
                    style="background-color: #f1f5f9; border-left: 4px solid #2563eb; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
                    <p style="margin: 0; font-size: 16px; color: #1e293b;">
                        <strong style="color: #2563eb;">Saludos
                            {{ explode(' ', $detail?->name ?? 'Empleado')[0] }},</strong>
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

                    @include('components.payment-voucher-table', ['detail' => $detail])
                </div>
            </div>

            <!-- Contact Information -->
            {{-- <div style="background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); padding: 20px; margin-top: 20px;">
                <div style="background-color: #ffffff; border-radius: 8px; padding: 20px; border-left: 4px solid #2563eb;">
                    <h3 style="color: #2563eb; font-size: 16px; font-weight: 600; margin: 0 0 12px 0;">
                        📞 Información de Contacto
                    </h3>
                    <div style="color: #475569; font-size: 14px; line-height: 1.6;">
                        <p style="margin: 0 0 8px 0;"><strong style="color: #1e293b;">Justina M. Villar Suazo</strong></p>
                        <p style="margin: 0 0 12px 0; color: #64748b; font-style: italic;">Contador Público Autorizado</p>
                        
                        <div style="display: inline-block; background-color: #f8fafc; padding: 8px 12px; border-radius: 4px; margin: 4px 0;">
                            <strong style="color: #2563eb;">🕰️ Horario:</strong> Lunes a Viernes, 8:00 AM - 5:00 PM
                        </div><br>
                        
                        <div style="display: inline-block; background-color: #f8fafc; padding: 8px 12px; border-radius: 4px; margin: 4px 0;">
                            <strong style="color: #2563eb;">📞 Oficina:</strong> 809-245-9233
                        </div><br>
                        
                        <div style="display: inline-block; background-color: #f8fafc; padding: 8px 12px; border-radius: 4px; margin: 4px 0;">
                            <strong style="color: #2563eb;">📱 Celular:</strong> 849-881-3340
                        </div><br>
                    </div>
                </div>
            </div> --}}

            {{-- Backup PDF Download Link --}}
            {{-- @if ($pdfOutput && $pdfName)
            <div style="background-color: #f3f4f6; padding: 20px; text-align: center; margin-top: 20px;">
                <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 14px;">
                    Si el botón no funciona, puedes usar este enlace directo:
                </p>
                <a href="data:application/pdf;base64,{{ base64_encode($pdfOutput) }}" 
                   download="{{ $pdfName }}"
                   style="color: #2563eb; text-decoration: underline; font-size: 14px;">
                    Descargar {{ $pdfName }}
                </a>
            </div>
            @endif --}}

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
                    © 2025 {{ $detail?->payroll?->company?->name ?? 'Empresa' }} • Sistema de Gestión de Nómina
                </p>
            </div>
        </div>
    </div>
</body>

</html>
