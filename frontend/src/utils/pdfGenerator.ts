import dayjs from 'dayjs';

// Interfaces para PDF
interface PurchaseItem {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number; // Cantidad en unidades de empaque
  quantityInBaseUnits?: number; // Cantidad en unidad base
  unit: string; // Unidad base del producto
  packagingUnitId?: string;
  packagingUnitName?: string;
  baseQuantityPerUnit?: number;
  unitPrice: number; // Precio por unidad de empaque
  subtotal: number;
  ivaPercentage?: number; // IVA del producto
  taxAmount?: number; // Monto de IVA del item
  total?: number; // Total del item con IVA
  expirationDate?: Date;
}

interface Supplier {
  id: string;
  name: string;
  nit: string;
  address: string;
  city: string;
  department: string;
  phone: string;
  email: string;
  contactPerson: string;
  contactPhone: string;
  paymentTerms: string;
  status: string;
}

interface Purchase {
  id: string;
  orderNumber: string;
  supplierId: string;
  supplierName: string;
  supplier?: Supplier;
  destinationLocationId: string;
  destinationLocationName: string;
  purchaseDate: Date;
  expectedDelivery?: Date;
  status: 'ordered' | 'in_transit' | 'received' | 'cancelled';
  items: PurchaseItem[];
  subtotal: number;
  tax: number;
  total: number;
  observations?: string;
  attachments: string[];
  createdBy: string;
  receivedBy?: string;
  receivedAt?: Date;
  createdAt: Date;
}

export interface PDFGeneratorOptions {
  companyName?: string;
  companyAddress?: string;
  companyPhone?: string;
  companyEmail?: string;
  logo?: string;
}

export class PDFGenerator {
  private options: PDFGeneratorOptions;

  constructor(options: PDFGeneratorOptions = {}) {
    this.options = {
      companyName: 'AgriFlor S.A.S.',
      companyAddress: 'Calle 123 #45-67, Bogotá, Colombia',
      companyPhone: '+57 (1) 234-5678',
      companyEmail: 'compras@agriflor.com',
      ...options
    };
  }

  /**
   * Genera un PDF de orden de compra
   */
  async generatePurchaseOrderPDF(purchase: Purchase): Promise<string> {
    // Simulamos la generación del PDF
    // En una implementación real usarías una librería como jsPDF o PDFKit

    const pdfContent = this.generatePurchaseOrderHTML(purchase);

    // Simular URL del PDF generado
    const pdfUrl = `data:text/html;charset=utf-8,${encodeURIComponent(pdfContent)}`;

    return pdfUrl;
  }

  /**
   * Genera el HTML para el PDF de orden de compra
   */
  private generatePurchaseOrderHTML(purchase: Purchase): string {
    // Usar valores de la compra (calculados con IVA por producto en el backend)
    const subtotalAmount = purchase.subtotal;
    const taxAmount = purchase.tax;
    const totalAmount = purchase.total;

    // Determinar si hay IVA mixto (diferentes porcentajes por producto)
    const ivaPercentages = [...new Set(purchase.items.map(item => item.ivaPercentage ?? 19))];
    const isMixedIva = ivaPercentages.length > 1;
    const singleIvaPercentage = isMixedIva ? null : ivaPercentages[0];

    return `
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Compra - ${purchase.orderNumber}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: white;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 2px solid #2E7D32;
            padding-bottom: 20px;
        }

        .company-info {
            flex: 1;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2E7D32;
            margin-bottom: 5px;
        }

        .company-details {
            color: #666;
            line-height: 1.6;
        }

        .order-info {
            text-align: right;
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
        }

        .order-title {
            font-size: 18px;
            font-weight: bold;
            color: #2E7D32;
            margin-bottom: 10px;
        }

        .order-details {
            line-height: 1.8;
        }

        .supplier-section {
            margin: 30px 0;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2E7D32;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .supplier-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .products-section {
            margin: 30px 0;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .products-table th,
        .products-table td {
            border: 1px solid #ddd;
            padding: 12px 8px;
            text-align: left;
        }

        .products-table th {
            background: #2E7D32;
            color: white;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        .products-table td {
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals-section {
            margin: 30px 0;
            display: flex;
            justify-content: flex-end;
        }

        .totals-table {
            width: 300px;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
        }

        .totals-table .label {
            background: #f5f5f5;
            font-weight: bold;
            text-align: right;
        }

        .totals-table .total-row {
            background: #2E7D32;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .terms-section {
            margin: 30px 0;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
        }

        .terms-list {
            list-style: none;
            padding: 0;
        }

        .terms-list li {
            margin: 8px 0;
            padding-left: 15px;
            position: relative;
        }

        .terms-list li:before {
            content: "•";
            color: #2E7D32;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 10px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }

        .signatures {
            margin: 40px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .signature-box {
            text-align: center;
            border-top: 1px solid #333;
            padding-top: 10px;
        }

        .observations {
            margin: 20px 0;
            background: #fff9c4;
            border: 1px solid #f7dc6f;
            border-radius: 5px;
            padding: 15px;
        }

        @media print {
            .container {
                max-width: none;
                margin: 0;
                padding: 0;
            }

            body {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <div class="company-name">${this.options.companyName}</div>
                <div class="company-details">
                    ${this.options.companyAddress}<br>
                    Tel: ${this.options.companyPhone}<br>
                    Email: ${this.options.companyEmail}
                </div>
            </div>
            <div class="order-info">
                <div class="order-title">ORDEN DE COMPRA</div>
                <div class="order-details">
                    <strong>No:</strong> ${purchase.orderNumber}<br>
                    <strong>Fecha:</strong> ${dayjs(purchase.purchaseDate).format('DD/MM/YYYY')}<br>
                    ${purchase.expectedDelivery ? `<strong>Entrega:</strong> ${dayjs(purchase.expectedDelivery).format('DD/MM/YYYY')}<br>` : ''}
                    <strong>Estado:</strong> ${this.getStatusText(purchase.status)}
                </div>
            </div>
        </div>

        <!-- Supplier Information -->
        <div class="supplier-section">
            <div class="section-title">DATOS DEL PROVEEDOR</div>
            <div class="supplier-details">
                <div>
                    <strong>Razón Social:</strong><br>
                    ${purchase.supplier?.name || purchase.supplierName}<br><br>
                    <strong>NIT:</strong><br>
                    ${purchase.supplier?.nit || 'No disponible'}<br><br>
                    <strong>Dirección:</strong><br>
                    ${purchase.supplier?.address || 'No disponible'}<br>
                    ${purchase.supplier?.city || ''} ${purchase.supplier?.department ? ', ' + purchase.supplier.department : ''}
                </div>
                <div>
                    <strong>Contacto:</strong><br>
                    ${purchase.supplier?.contactPerson || 'No disponible'}<br><br>
                    <strong>Teléfono:</strong><br>
                    ${purchase.supplier?.phone || 'No disponible'}<br><br>
                    <strong>Celular:</strong><br>
                    ${purchase.supplier?.contactPhone || 'No disponible'}<br><br>
                    <strong>Email:</strong><br>
                    ${purchase.supplier?.email || 'No disponible'}
                </div>
            </div>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <strong>Términos de Pago:</strong> ${purchase.supplier?.paymentTerms || '30 días calendario'}
            </div>
        </div>

        <!-- Delivery Information -->
        <div class="supplier-section">
            <div class="section-title">INFORMACIÓN DE ENTREGA</div>
            <div class="supplier-details">
                <div>
                    <strong>Entregar en:</strong><br>
                    ${purchase.destinationLocationName}<br><br>
                    <strong>Instrucciones:</strong><br>
                    Coordinar entrega con responsable de la ubicación
                </div>
                <div>
                    <strong>Fecha de Entrega:</strong><br>
                    ${purchase.expectedDelivery ? dayjs(purchase.expectedDelivery).format('DD/MM/YYYY') : 'Por coordinar'}<br><br>
                    <strong>Horarios de Entrega:</strong><br>
                    Lunes a Viernes: 8:00 AM - 4:00 PM<br>
                    Sábados: 8:00 AM - 12:00 PM
                </div>
            </div>
        </div>

        <!-- Products -->
        <div class="products-section">
            <div class="section-title">PRODUCTOS SOLICITADOS</div>
            <table class="products-table">
                <thead>
                    <tr>
                        <th style="width: 5%">#</th>
                        <th style="width: 22%">Producto</th>
                        <th style="width: 10%">Marca</th>
                        <th style="width: 7%">Cantidad</th>
                        <th style="width: 10%">Unidad Empaque</th>
                        <th style="width: 12%">Equivalencia Total</th>
                        <th style="width: 9%">Precio Unit.</th>
                        <th style="width: 5%">IVA</th>
                        <th style="width: 10%">Total</th>
                        ${purchase.items.some(item => item.expirationDate) ? '<th style="width: 8%">Vencimiento</th>' : ''}
                    </tr>
                </thead>
                <tbody>
                    ${purchase.items.map((item, index) => {
                        const equivalenceText = item.packagingUnitName && item.baseQuantityPerUnit && item.quantityInBaseUnits ?
                            `${item.quantity} ${item.packagingUnitName}${item.quantity > 1 ? 's' : ''} × ${item.baseQuantityPerUnit} ${item.unit} = ${item.quantityInBaseUnits} ${item.unit}` :
                            `${item.quantity} ${item.unit}`;
                        const itemIva = item.ivaPercentage ?? 19;
                        const itemTotal = item.total ?? (item.subtotal * (1 + itemIva / 100));

                        return `
                        <tr>
                            <td class="text-center">${index + 1}</td>
                            <td>${item.productName}</td>
                            <td>${item.brandName}</td>
                            <td class="text-right">${item.quantity.toLocaleString()}</td>
                            <td class="text-center">${item.packagingUnitName || item.unit}</td>
                            <td class="text-center" style="font-size: 10px; color: #0958d9; font-weight: bold;">${equivalenceText}</td>
                            <td class="text-right">$${item.unitPrice.toLocaleString()}</td>
                            <td class="text-center">${itemIva}%</td>
                            <td class="text-right">$${Math.round(itemTotal).toLocaleString()}</td>
                            ${purchase.items.some(i => i.expirationDate) ?
                                `<td class="text-center">${item.expirationDate ? dayjs(item.expirationDate).format('DD/MM/YYYY') : 'N/A'}</td>` :
                                ''
                            }
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="text-right">$${subtotalAmount.toLocaleString()}</td>
                </tr>
                <tr>
                    <td class="label">IVA${singleIvaPercentage !== null ? ` (${singleIvaPercentage}%)` : ' (Mixto)'}:</td>
                    <td class="text-right">$${taxAmount.toLocaleString()}</td>
                </tr>
                <tr class="total-row">
                    <td class="label">TOTAL:</td>
                    <td class="text-right">$${totalAmount.toLocaleString()}</td>
                </tr>
            </table>
        </div>

        <!-- Observations -->
        ${purchase.observations ? `
        <div class="observations">
            <div class="section-title">OBSERVACIONES</div>
            <p>${purchase.observations}</p>
        </div>
        ` : ''}

        <!-- Terms and Conditions -->
        <div class="terms-section">
            <div class="section-title">TÉRMINOS Y CONDICIONES</div>
            <ul class="terms-list">
                <li>Los productos deben cumplir con las especificaciones técnicas solicitadas.</li>
                <li>La entrega debe realizarse en la fecha acordada.</li>
                <li>Los productos deben estar en perfecto estado y con fecha de vencimiento vigente.</li>
                <li>Se requiere factura legal con todos los datos fiscales correctos.</li>
                <li>Cualquier daño o defecto debe ser reportado inmediatamente.</li>
                <li>El pago se realizará según los términos acordados previa entrega conforme.</li>
            </ul>
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-box">
                <div style="margin-bottom: 40px;"></div>
                <div><strong>Solicitado por</strong></div>
                <div>${purchase.createdBy}</div>
                <div>Depto. de Compras</div>
            </div>
            <div class="signature-box">
                <div style="margin-bottom: 40px;"></div>
                <div><strong>Aprobado por</strong></div>
                <div>_________________</div>
                <div>Gerencia</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Este documento fue generado automáticamente por el sistema AgriFlor el ${dayjs().format('DD/MM/YYYY HH:mm')}</p>
            <p>Para cualquier consulta contactar al ${this.options.companyPhone} o ${this.options.companyEmail}</p>
        </div>
    </div>

    <script>
        // Auto-print when opened
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>`;
  }

  /**
   * Convierte el estado de la compra a texto legible
   */
  private getStatusText(status: string): string {
    const statusMap: { [key: string]: string } = {
      'ordered': 'Ordenado',
      'in_transit': 'En Tránsito',
      'received': 'Recibido',
      'cancelled': 'Cancelado'
    };
    return statusMap[status] || status;
  }

  /**
   * Descarga el PDF generado
   */
  async downloadPurchaseOrderPDF(purchase: Purchase): Promise<void> {
    try {
      const pdfUrl = await this.generatePurchaseOrderPDF(purchase);

      // Crear un enlace temporal para descargar el HTML como archivo
      const link = document.createElement('a');
      link.href = pdfUrl;
      link.download = `orden_compra_${purchase.orderNumber}.html`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      // Nota: En una implementación real, aquí generarías un PDF real
      console.log('PDF descargado:', `orden_compra_${purchase.orderNumber}.html`);
    } catch (error) {
      console.error('Error al descargar PDF:', error);
      throw new Error('No se pudo generar el PDF de la orden de compra');
    }
  }

  /**
   * Abre el PDF en una nueva ventana
   */
  async openPurchaseOrderPDF(purchase: Purchase): Promise<void> {
    try {
      const pdfUrl = await this.generatePurchaseOrderPDF(purchase);
      const newWindow = window.open('', '_blank', 'width=800,height=600');

      if (newWindow) {
        newWindow.document.write(pdfUrl.split(',')[1] ? atob(pdfUrl.split(',')[1]) : '');
        newWindow.document.close();
        newWindow.focus();
      } else {
        throw new Error('No se pudo abrir la ventana del PDF');
      }
    } catch (error) {
      console.error('Error al abrir PDF:', error);
      throw new Error('No se pudo abrir el PDF de la orden de compra');
    }
  }
}

// Instancia por defecto del generador de PDF
export const pdfGenerator = new PDFGenerator();

// Funciones de utilidad para uso directo
export const generatePurchaseOrderPDF = (purchase: Purchase) =>
  pdfGenerator.generatePurchaseOrderPDF(purchase);

export const downloadPurchaseOrderPDF = (purchase: Purchase) =>
  pdfGenerator.downloadPurchaseOrderPDF(purchase);

export const openPurchaseOrderPDF = (purchase: Purchase) =>
  pdfGenerator.openPurchaseOrderPDF(purchase);