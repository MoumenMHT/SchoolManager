# School Payment System - Complete Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [Database Architecture](#database-architecture)
3. [Workflow Phases](#workflow-phases)
4. [API Endpoints](#api-endpoints)
5. [Usage Examples](#usage-examples)
6. [Business Logic](#business-logic)

---

## System Overview

This is a comprehensive school payment management system that handles:
- Fee configuration by administrators
- Parent enrollment and contract creation
- Automatic bill generation
- Payment processing with automatic allocation
- Mid-year service changes
- Student withdrawals and refunds
- Financial reporting and tracking

### Key Features
✅ Automatic payment allocation to unpaid bills  
✅ Support for partial payments  
✅ Support for advance payments (multiple months)  
✅ Overpayment tracking (stored as contract balance)  
✅ Refund processing  
✅ Mid-year contract modifications  
✅ Withdrawal management  
✅ Parent and Admin dashboards  
✅ Comprehensive financial reporting  

---

## Database Architecture

### 1. Fees Table
Stores all available fees that can be assigned to contracts.

```sql
CREATE TABLE fees (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    base_amount DECIMAL(10,2) NOT NULL,
    academic_year VARCHAR(60) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Purpose:** Admin creates fees during Phase 1 setup.

---

### 2. Parents_Fees Table (Junction Table)
Links parents to the fees they've selected for their contracts.

```sql
CREATE TABLE parents_fees (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    parent_id BIGINT NOT NULL,
    fee_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    UNIQUE KEY (parent_id, fee_id)
);
```

**Purpose:** Phase 2.3 - Records which services/fees parent selected.

---

### 3. Contracts Table
Main contract between school and parent for an academic year.

```sql
CREATE TABLE contracts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    parent_id BIGINT NOT NULL,
    contract_number VARCHAR(255) UNIQUE NOT NULL,
    academic_year VARCHAR(60) NOT NULL,
    total_fees DECIMAL(10,2) NOT NULL,
    discount_type VARCHAR(60),
    discount_value DECIMAL(10,2) DEFAULT 0,
    discount_reason TEXT,
    monthly_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    remaining_amount DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0,  -- For overpayments
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    notes TEXT,
    status VARCHAR(50) DEFAULT 'active',  -- active, completed, cancelled, withdrawn, superseded
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);
```

**Purpose:** Phase 2.5 - Created after parent selects services.

**Key Fields:**
- `paid_amount`: Total amount paid so far
- `remaining_amount`: Amount still owed
- `balance`: Overpayment/credit amount (positive = parent has credit)
- `status`: Contract lifecycle status

---

### 4. Bills Table
Monthly bills auto-generated from contract.

```sql
CREATE TABLE bills (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    contract_id BIGINT NOT NULL,
    month_year VARCHAR(60) NOT NULL,  -- e.g., "March 2025"
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    balance DECIMAL(10,2) NOT NULL,  -- amount_due - amount_paid
    status VARCHAR(60) DEFAULT 'unpaid',  -- unpaid, partial, paid, late, cancelled
    due_date DATE NOT NULL,
    note TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
);
```

**Purpose:** Phase 2.6 - Auto-generated for each month of contract.

**Status Values:**
- `unpaid`: No payment made
- `partial`: Some payment made, but not full amount
- `paid`: Fully paid
- `late`: Past due date and unpaid
- `cancelled`: Contract terminated, bill cancelled

---

### 5. Payments Table
Records all payments made by parents.

```sql
CREATE TABLE payments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT,  -- Optional, for backward compatibility
    contract_id BIGINT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type VARCHAR(100),  -- cash, card, bank_transfer, refund
    status VARCHAR(50) DEFAULT 'completed',  -- completed, pending, cancelled, refunded
    paid_date DATETIME,
    note TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
);
```

**Purpose:** Phase 3 - Records every payment transaction.

---

### 6. Payment_Allocations Table
Tracks which bills each payment was applied to.

```sql
CREATE TABLE payment_allocations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payment_id BIGINT NOT NULL,
    bill_id BIGINT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
);
```

**Purpose:** Phase 3 - Creates detailed audit trail of payment distribution.

**Example:**
Parent pays $1000:
- $500 → March bill
- $500 → April bill
Two allocation records created.

---

## Workflow Phases

### Phase 1: Setup & Configuration

**1.1 Admin Creates Fees**

Admin sets up available fees for the academic year.

**API Endpoint:** `POST /api/fees`

**Request:**
```json
{
    "name": "Tuition Fee",
    "description": "Monthly tuition for Grade 1",
    "base_amount": 500.00,
    "academic_year": "2024-2025",
    "is_active": true
}
```

**Response:**
```json
{
    "success": true,
    "message": "Fee created successfully and available for contract creation",
    "data": {
        "id": 1,
        "name": "Tuition Fee",
        "base_amount": 500.00,
        "academic_year": "2024-2025"
    }
}
```

**Additional Fee Management:**
- `GET /api/fees` - List all fees
- `GET /api/fees/{id}` - View fee details with usage statistics
- `PUT /api/fees/{id}` - Update fee (protected if in active contracts)
- `DELETE /api/fees/{id}` - Delete or deactivate fee
- `POST /api/fees/bulk` - Create multiple fees at once
- `POST /api/fees/copy-to-new-year` - Copy fees to new academic year
- `GET /api/fees/available-for-contract?academic_year=2024-2025` - Get active fees
- `GET /api/fees/statistics` - Fee analytics

---

### Phase 2: Parent Enrollment & Contract Creation

**2.1 Parent Registers/Logs In**
(Handled by existing authentication system)

**2.2 Parent Selects Services**

Parent chooses which fees/services they want.

**2.3 System Creates parents_fees Records**
**2.4 System Calculates Total with Discount**
**2.5 System Creates Contract**
**2.6 System Auto-Generates Monthly Bills**
**2.7 Parent Receives Contract**

**API Endpoint:** `POST /api/contracts`

**Request:**
```json
{
    "parent_id": 5,
    "fee_ids": [1, 2, 3],
    "academic_year": "2024-2025",
    "start_date": "2024-09-01",
    "end_date": "2025-06-30",
    "discount_type": "percentage",
    "discount_value": 10,
    "discount_reason": "Sibling discount",
    "notes": "Payment plan for Student John Doe"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Contract created successfully",
    "data": {
        "contract": {
            "id": 1,
            "contract_number": "CNT-2024-000001",
            "parent_id": 5,
            "academic_year": "2024-2025",
            "total_fees": 5000.00,
            "discount_value": 500.00,
            "monthly_amount": 450.00,
            "paid_amount": 0,
            "remaining_amount": 4500.00,
            "balance": 0,
            "start_date": "2024-09-01",
            "end_date": "2025-06-30",
            "status": "active",
            "bills": [
                {
                    "id": 1,
                    "month_year": "September 2024",
                    "amount_due": 450.00,
                    "amount_paid": 0,
                    "balance": 450.00,
                    "status": "unpaid",
                    "due_date": "2024-09-30"
                },
                // ... 9 more bills
            ]
        },
        "summary": {
            "total_fees": 5000.00,
            "discount_applied": 500.00,
            "final_amount": 4500.00,
            "monthly_amount": 450.00,
            "number_of_months": 10,
            "bills_generated": 10
        }
    }
}
```

**What Happens:**
1. ✅ Validates parent exists and fees are valid
2. ✅ Calculates total from selected fees
3. ✅ Applies discount (percentage or fixed)
4. ✅ Creates contract record with unique contract number
5. ✅ Creates parent_fees records for tracking
6. ✅ Auto-generates 10 monthly bills (Sept-June)
7. ✅ Returns complete contract with all bills

---

### Phase 3: Payment Processing

#### Scenario A: Regular Monthly Payment

Parent pays for one month (e.g., March bill = $450).

**API Endpoint:** `POST /api/payments`

**Request:**
```json
{
    "contract_id": 1,
    "amount": 450.00,
    "payment_type": "bank_transfer",
    "paid_date": "2025-03-15",
    "note": "March payment"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment processed successfully",
    "data": {
        "id": 1,
        "contract_id": 1,
        "amount": 450.00,
        "payment_type": "bank_transfer",
        "status": "completed",
        "paid_date": "2025-03-15",
        "allocations": [
            {
                "bill_id": 7,
                "amount": 450.00,
                "bill": {
                    "month_year": "March 2025",
                    "status": "paid"
                }
            }
        ]
    },
    "overpayment": 0
}
```

**What Happens:**
1. ✅ Creates payment record
2. ✅ Finds first unpaid bill (March)
3. ✅ Allocates $450 to March bill
4. ✅ Updates March bill: `amount_paid = 450`, `status = 'paid'`
5. ✅ Updates contract: `paid_amount += 450`, `remaining_amount -= 450`
6. ✅ Creates allocation record linking payment to bill

---

#### Scenario B: Advance Payment (Multiple Months)

Parent pays $1800 to cover 4 months ahead.

**Request:**
```json
{
    "contract_id": 1,
    "amount": 1800.00,
    "payment_type": "cash",
    "paid_date": "2025-03-15",
    "note": "Advance payment for 4 months"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment processed successfully",
    "data": {
        "id": 2,
        "amount": 1800.00,
        "allocations": [
            {
                "bill_id": 7,
                "amount": 450.00,
                "bill": { "month_year": "March 2025", "status": "paid" }
            },
            {
                "bill_id": 8,
                "amount": 450.00,
                "bill": { "month_year": "April 2025", "status": "paid" }
            },
            {
                "bill_id": 9,
                "amount": 450.00,
                "bill": { "month_year": "May 2025", "status": "paid" }
            },
            {
                "bill_id": 10,
                "amount": 450.00,
                "bill": { "month_year": "June 2025", "status": "paid" }
            }
        ]
    },
    "overpayment": 0
}
```

**What Happens:**
1. ✅ Payment of $1800 received
2. ✅ System finds 4 unpaid bills (March, April, May, June)
3. ✅ Distributes $450 to each bill in chronological order
4. ✅ All 4 bills marked as 'paid'
5. ✅ 4 allocation records created
6. ✅ Contract updated with total paid amount

---

#### Scenario C: Overpayment

Parent pays $2000 but only owes $1800.

**Request:**
```json
{
    "contract_id": 1,
    "amount": 2000.00,
    "payment_type": "card",
    "paid_date": "2025-03-15"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment processed successfully",
    "data": {
        "id": 3,
        "amount": 2000.00,
        "allocations": [
            // ... allocations to 4 bills totaling $1800
        ]
    },
    "overpayment": 200.00
}
```

**What Happens:**
1. ✅ Payment of $2000 received
2. ✅ Allocates $1800 to unpaid bills
3. ✅ Remaining $200 stored in `contract.balance`
4. ✅ This credit can be used for future payments or refunded
5. ✅ Parent notified of credit balance

**Contract after overpayment:**
```json
{
    "paid_amount": 2000.00,
    "remaining_amount": 2700.00,
    "balance": 200.00  // Credit balance
}
```

---

#### Scenario D: Partial Payment

Parent pays $200 but bill is $450.

**Request:**
```json
{
    "contract_id": 1,
    "amount": 200.00,
    "payment_type": "cash",
    "paid_date": "2025-03-15"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment processed successfully",
    "data": {
        "allocations": [
            {
                "bill_id": 7,
                "amount": 200.00,
                "bill": {
                    "month_year": "March 2025",
                    "amount_due": 450.00,
                    "amount_paid": 200.00,
                    "balance": 250.00,
                    "status": "partial"
                }
            }
        ]
    }
}
```

**What Happens:**
1. ✅ Payment of $200 applied to March bill
2. ✅ March bill status = 'partial'
3. ✅ Balance on bill = $250
4. ✅ Next payment will complete this bill first

---

#### Scenario E: Multiple Payments for One Bill

Parent makes two payments to complete one bill.

**First Payment:**
```json
{
    "amount": 200.00,
    "paid_date": "2025-03-15"
}
```

**Second Payment:**
```json
{
    "amount": 250.00,
    "paid_date": "2025-03-20"
}
```

**Result:**
```json
{
    "bill": {
        "month_year": "March 2025",
        "amount_due": 450.00,
        "amount_paid": 450.00,
        "status": "paid",
        "payment_allocations": [
            {
                "payment_id": 1,
                "amount": 200.00,
                "paid_date": "2025-03-15"
            },
            {
                "payment_id": 2,
                "amount": 250.00,
                "paid_date": "2025-03-20"
            }
        ]
    }
}
```

---

### Phase 4: Mid-Year Changes

**4.1 Parent Wants to Add Service**

Parent wants to add "Transportation" service starting April.

**API Endpoint:** `POST /api/contracts/{id}/add-service`

**Request:**
```json
{
    "new_fee_ids": [4],  // Transportation fee
    "effective_date": "2025-04-01",
    "reason": "Parent requested transportation service"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Service added successfully. New contract created.",
    "data": {
        "old_contract": {
            "id": 1,
            "contract_number": "CNT-2024-000001",
            "status": "superseded"
        },
        "new_contract": {
            "id": 2,
            "contract_number": "CNT-2024-000001-V2",
            "monthly_amount": 550.00,
            "status": "active"
        },
        "changes": {
            "additional_monthly_cost": 100.00,
            "new_monthly_amount": 550.00,
            "remaining_months": 3,
            "additional_total_cost": 300.00
        }
    }
}
```

**What Happens:**
1. ✅ Calculates new total with additional service
2. ✅ Creates new contract version (CNT-2024-000001-V2)
3. ✅ Deletes future unpaid bills from old contract
4. ✅ Generates new bills with updated amount
5. ✅ Marks old contract as 'superseded'
6. ✅ Transfers paid amount and balance to new contract

---

### Phase 5: Student Withdrawal

**5.1 Parent Withdraws Child**

Parent withdraws child effective April 1st.

**API Endpoint:** `POST /api/contracts/{id}/withdraw`

**Request:**
```json
{
    "withdrawal_date": "2025-04-01",
    "withdrawal_reason": "Family relocating to another city"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Withdrawal processed successfully",
    "data": {
        "original_contract": {
            "id": 1,
            "status": "withdrawn"
        },
        "final_contract": {
            "id": 3,
            "contract_number": "CNT-2024-000001-FINAL",
            "status": "completed"
        },
        "financial_summary": {
            "months_consumed": 7,
            "amount_for_consumed_months": 3150.00,
            "amount_paid": 3600.00,
            "refund_due": 450.00,
            "status": "refund_pending"
        }
    }
}
```

**What Happens:**
1. ✅ Calculates months consumed (Sept-March = 7 months)
2. ✅ Amount owed: 7 × $450 = $3,150
3. ✅ Amount paid: $3,600
4. ✅ Refund due: $450
5. ✅ Creates final contract with correct totals
6. ✅ Cancels all future bills
7. ✅ Marks original contract as 'withdrawn'

---

### Phase 6: Reporting & Tracking

#### 6.1 Parent Dashboard

View all contracts and payment status for a parent.

**API Endpoint:** `GET /api/payments/parent-dashboard/{parentId}`

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "contract_id": 1,
            "contract_number": "CNT-2024-000001",
            "academic_year": "2024-2025",
            "total_amount": 4500.00,
            "paid_amount": 3600.00,
            "remaining_amount": 900.00,
            "balance": 0,
            "monthly_amount": 450.00,
            "next_due_date": "2025-04-30",
            "unpaid_bills_count": 2,
            "late_bills_count": 0,
            "last_payment": {
                "id": 5,
                "amount": 450.00,
                "paid_date": "2025-03-15"
            }
        }
    ]
}
```

---

#### 6.2 Admin Dashboard

Financial reports for administrators.

**API Endpoint:** `GET /api/payments/financial-reports`

**Query Parameters:**
```
?start_date=2024-09-01
&end_date=2025-06-30
&academic_year=2024-2025
```

**Response:**
```json
{
    "success": true,
    "data": {
        "total_payments": 150,
        "total_amount_collected": 67500.00,
        "total_refunds": -450.00,
        "net_amount": 67050.00,
        "payment_by_type": {
            "cash": { "count": 45, "total": 20250.00 },
            "card": { "count": 60, "total": 27000.00 },
            "bank_transfer": { "count": 45, "total": 20250.00 }
        },
        "contracts_summary": [
            {
                "contract_number": "CNT-2024-000001",
                "parent_name": "John Smith",
                "total_fees": 4500.00,
                "paid_amount": 3600.00,
                "remaining_amount": 900.00,
                "payment_completion": 80.00
            }
            // ... more contracts
        ]
    }
}
```

---

#### 6.3 Contract Statistics

Detailed statistics for a specific contract.

**API Endpoint:** `GET /api/payments/contract-statistics/{contractId}`

**Response:**
```json
{
    "success": true,
    "data": {
        "contract_number": "CNT-2024-000001",
        "academic_year": "2024-2025",
        "total_contract_amount": 4500.00,
        "total_paid": 3600.00,
        "total_remaining": 900.00,
        "overpayment_balance": 0,
        "monthly_amount": 450.00,
        "bills_summary": {
            "total_bills": 10,
            "paid_bills": 8,
            "unpaid_bills": 2,
            "partial_bills": 0
        },
        "payment_summary": {
            "total_payments": 8,
            "total_payment_amount": 3600.00,
            "last_payment_date": "2025-03-15"
        },
        "next_due_bill": {
            "id": 9,
            "month_year": "April 2025",
            "amount_due": 450.00,
            "due_date": "2025-04-30"
        }
    }
}
```

---

### Phase 7: End of Year

**7.1 Contract Completion**

When final bill (June) is paid:

```json
{
    "contract": {
        "status": "completed",
        "paid_amount": 4500.00,
        "remaining_amount": 0,
        "all_bills_paid": true
    }
}
```

**7.2 New Year Renewal**

Admin copies fees to new academic year, parent creates new contract.

---

## API Endpoints Reference

### Fee Management
```
POST   /api/fees                          - Create fee
GET    /api/fees                          - List all fees
GET    /api/fees/{id}                     - Get fee details
PUT    /api/fees/{id}                     - Update fee
DELETE /api/fees/{id}                     - Delete/deactivate fee
POST   /api/fees/bulk                     - Bulk create fees
POST   /api/fees/copy-to-new-year         - Copy fees to new year
GET    /api/fees/available-for-contract   - Get active fees
GET    /api/fees/statistics               - Fee statistics
PUT    /api/fees/{id}/toggle-status       - Toggle active status
```

### Contract Management
```
POST   /api/contracts                     - Create contract
GET    /api/contracts                     - List contracts
GET    /api/contracts/{id}                - Get contract details
POST   /api/contracts/{id}/add-service    - Add service mid-year
POST   /api/contracts/{id}/withdraw       - Process withdrawal
```

### Payment Processing
```
POST   /api/payments                      - Process payment
GET    /api/payments                      - List payments
GET    /api/payments/{id}                 - Get payment details
POST   /api/payments/{id}/refund          - Process refund
GET    /api/payments/{id}/receipt         - Get payment receipt
POST   /api/payments/calculate            - Calculate payment allocation (preview)
```

### Bill Management
```
GET    /api/bills                         - List bills
GET    /api/bills/{id}                    - Get bill details
GET    /api/bills/contract/{id}/unpaid    - Get unpaid bills
```

### Reporting & Analytics
```
GET    /api/payments/contract-payments/{contractId}      - Payment history
GET    /api/payments/contract-statistics/{contractId}    - Contract statistics
GET    /api/payments/payment-history/{contractId}        - Detailed payment tracking
GET    /api/payments/parent-dashboard/{parentId}         - Parent dashboard
GET    /api/payments/financial-reports                   - Admin financial reports
```

---

## Business Logic

### Payment Allocation Algorithm

```
When payment is received:
1. Find all unpaid bills for the contract
2. Order bills by due_date ascending
3. For each bill:
   a. Calculate bill balance (amount_due - amount_paid)
   b. Allocate minimum of (remaining_payment, bill_balance)
   c. Create allocation record
   d. Update bill amount_paid
   e. Update bill status
   f. Subtract allocated amount from remaining_payment
4. If remaining_payment > 0:
   a. Add to contract.balance (overpayment)
5. Update contract paid_amount and remaining_amount
```

### Bill Status Logic

```
Status determination:
- unpaid: amount_paid = 0
- partial: 0 < amount_paid < amount_due
- paid: amount_paid >= amount_due
- late: unpaid AND due_date < today
- cancelled: contract withdrawn/terminated
```

### Discount Calculation

```
If discount_type = 'fixed':
    final_amount = total_fees - discount_value

If discount_type = 'percentage':
    discount_value = (total_fees × discount_value) / 100
    final_amount = total_fees - discount_value

monthly_amount = final_amount / number_of_months
```

---

## Usage Examples

### Example 1: Complete Payment Flow

```php
// Step 1: Admin creates fees
POST /api/fees
{
    "name": "Tuition",
    "base_amount": 400,
    "academic_year": "2024-2025"
}

// Step 2: Parent creates contract
POST /api/contracts
{
    "parent_id": 1,
    "fee_ids": [1, 2, 3],
    "academic_year": "2024-2025",
    "start_date": "2024-09-01",
    "end_date": "2025-06-30"
}

// Response: Contract with 10 bills created

// Step 3: Parent makes first payment
POST /api/payments
{
    "contract_id": 1,
    "amount": 400,
    "payment_type": "cash",
    "paid_date": "2024-09-05"
}

// Response: September bill marked as paid

// Step 4: Check payment history
GET /api/payments/payment-history/1

// Step 5: View parent dashboard
GET /api/payments/parent-dashboard/1
```

### Example 2: Advance Payment

```php
// Parent pays for entire year upfront
POST /api/payments
{
    "contract_id": 1,
    "amount": 4500,
    "payment_type": "bank_transfer",
    "paid_date": "2024-09-01"
}

// System automatically:
// - Allocates to all 10 bills
// - Marks all bills as 'paid'
// - Creates 10 allocation records
// - Updates contract as fully paid
```

### Example 3: Mid-Year Service Addition

```php
// January: Parent adds lunch service
POST /api/contracts/1/add-service
{
    "new_fee_ids": [5],
    "effective_date": "2025-01-01",
    "reason": "Adding lunch service"
}

// System:
// - Creates new contract version
// - Adjusts remaining 6 months
// - New monthly amount calculated
// - New bills generated
```

### Example 4: Withdrawal with Refund

```php
// March: Family moves away
POST /api/contracts/1/withdraw
{
    "withdrawal_date": "2025-03-15",
    "withdrawal_reason": "Relocation"
}

// System calculates:
// - 6 months consumed × $450 = $2,700 owed
// - Actually paid: $3,150
// - Refund due: $450

// Then can process refund:
POST /api/payments/5/refund
{
    "refund_amount": 450,
    "refund_reason": "Withdrawal refund"
}
```

---

## Error Handling

All endpoints return standardized error responses:

```json
{
    "success": false,
    "message": "Human-readable error message",
    "error": "Technical error details",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `422` - Validation Error
- `404` - Not Found
- `500` - Server Error

---

## Security Considerations

1. **Payment Validation**
   - All amounts validated as positive numbers
   - Contract existence verified before payment
   - Prevents negative payments (except refunds)

2. **Contract Protection**
   - Cannot modify fee amounts in active contracts
   - Cannot delete fees used in contracts
   - Fees only deactivated, not deleted

3. **Refund Controls**
   - Refund amount cannot exceed original payment
   - Refund creates negative payment record
   - Complete audit trail maintained

4. **Transaction Integrity**
   - All operations use database transactions
   - Rollback on any error
   - Atomic payment allocation

---

## Database Relationships

```
fees (1) ─────── (many) parents_fees (many) ─────── (1) parents
                                                          │
                                                          │
                                                     (1)  │
                                                          ▼
                                                    contracts (1)
                                                          │
                                                          ├─── (many) bills
                                                          │
                                                          └─── (many) payments (1)
                                                                         │
                                                                         └─── (many) payment_allocations (many) ─── (1) bills
```

---

## Testing Checklist

- [ ] Fee creation and management
- [ ] Contract creation with fee selection
- [ ] Automatic bill generation
- [ ] Regular monthly payment
- [ ] Advance payment (multiple months)
- [ ] Partial payment
- [ ] Overpayment handling
- [ ] Multiple payments per bill
- [ ] Refund processing
- [ ] Mid-year service addition
- [ ] Withdrawal with refund calculation
- [ ] Parent dashboard accuracy
- [ ] Admin reports correctness
- [ ] Payment receipt generation
- [ ] Bill status updates
- [ ] Late payment marking

---

## Future Enhancements

- [ ] Automated late payment reminders
- [ ] Email notifications for payments/receipts
- [ ] Payment plan customization
- [ ] Multi-child family discounts
- [ ] Recurring payment setup
- [ ] Payment gateway integration
- [ ] SMS notifications
- [ ] Mobile app support
- [ ] Export reports to PDF/Excel
- [ ] Automated year-end processing

---

## Support

For issues or questions about this payment system:
1. Check this documentation
2. Review API endpoint responses
3. Check application logs
4. Contact development team

---

**Last Updated:** 2024
**Version:** 1.0
**Author:** School Management System Team
