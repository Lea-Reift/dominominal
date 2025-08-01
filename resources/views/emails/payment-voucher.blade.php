<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volante de Pago</title>
</head>
<body style="margin: 0; padding: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; color: #1f2937;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        {{-- Header --}}
        <div style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; padding: 24px; text-align: center;">
            <h1 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700;">{{ $detail?->payroll?->company?->name ?? 'Empresa' }}</h1>
            <p style="margin: 0; font-size: 16px; opacity: 0.9;">Volante de Pago</p>
        </div>

        {{-- Employee Info --}}
        <div style="padding: 20px; background-color: #f8fafc; border-bottom: 1px solid #e5e7eb;">
            <div style="display: inline-block; margin-right: 20px;">
                <strong style="color: #374151;">Empleado:</strong>
                <span style="color: #1f2937;">{{ $detail?->employee?->first_name ?? 'Empleado' }}</span>
            </div>
            <div style="display: inline-block;">
                <strong style="color: #374151;">Período:</strong>
                <span style="color: #1f2937;">{{ $detail?->payroll?->period?->format('d/m/Y') ?? 'N/A' }}</span>
            </div>
        </div>

        {{-- Anti-collapse barriers for Gmail --}}
        <div style="display: none; max-height: 0; overflow: hidden;">
            CONFIDENTIAL_INFORMATION_START_HIDDEN_FROM_PREVIEW_PANE_DO_NOT_SHOW_IN_EMAIL_LIST_VIEW_ONLY_WHEN_OPENED_FINANCIAL_DATA_BELOW
        </div>
        
        <div style="font-size: 0; line-height: 0; color: transparent; opacity: 0;">
            .&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;.&#8203;
        </div>

        {{-- Main Content --}}
        <div style="padding: 24px;">
            <div style="background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb;">
                @if($detail)
                    <x-voucher-table :detail="$detail" mode="email" />
                @else
                    <div style="padding: 40px; text-align: center; color: #6b7280;">
                        <p style="margin: 0; font-size: 16px;">No se pudieron cargar los detalles del volante de pago.</p>
                    </div>
                @endif
            </div>

            {{-- Backup PDF Download Link --}}
            @if($pdfOutput && $pdfName)
            <div style="margin-top: 20px; padding: 16px; background-color: #f3f4f6; border-radius: 8px; text-align: center;">
                <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 14px;">
                    Si no puedes ver correctamente la información anterior, puedes descargar el volante en PDF:
                </p>
                <a href="data:application/pdf;base64,{{ base64_encode($pdfOutput) }}" 
                   download="{{ $pdfName }}"
                   style="display: inline-block; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                    📄 Descargar PDF
                </a>
            </div>
            @endif
        </div>

        {{-- Footer --}}
        <div style="background-color: #f8fafc; padding: 24px; border-top: 1px solid #e5e7eb; text-align: center;">
            <p style="margin: 0 0 16px 0; color: #6b7280; font-size: 14px;">
                Saludos,<br>
                <strong>{{ $detail?->payroll?->company?->name ?? 'Empresa' }}</strong>
            </p>
            
            <div style="border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 16px;">
                <p style="margin: 0 0 8px 0; color: #9ca3af; font-size: 13px;">
                    Este correo se envió automáticamente y no requiere una respuesta.
                </p>
                <p style="margin: 0; color: #9ca3af; font-size: 13px;">
                    En caso de necesitarlo, puede contactar al correo {{ config('mail.from.address') }}.
                </p>
            </div>

            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                <p style="margin: 0; color: #6b7280; font-size: 12px; line-height: 1.5;">
                    <strong>Justina M. Villar Suazo</strong><br>
                    Contador Público Autorizado<br>
                    Horario de trabajo: Lunes a Viernes, 8:00 A.M a 5:00 P.M<br>
                    Tel. 809-245-9233 | Cel. 849-881-3340
                </p>
            </div>
        </div>
    </div>
</body>
</html>