"use client";

import { useForm } from "@/app/lib/form-context";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Info, Loader2, Upload, FileText, CheckCircle2, ChevronDown, AlertTriangle, Eraser } from "lucide-react";
import { AccountCard } from "./account-card";
import { cn } from "@/lib/utils";
import { ACCOUNT_TYPES } from "@/app/lib/account-types";
import { Textarea } from "@/components/ui/textarea";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { useRef, useState, useEffect } from "react";
import { Button } from "@/components/ui/button";

const STEP9_PCM_NOTICE = `This application may be supported by Prominence Client Management / Prominence Account Management (“PCM”), a separate legal entity acting as an independent introducer and providing administrative onboarding coordination only (intake support, document collection coordination, and application‑package transmission). PCM is not authorized to bind Prominence Bank or make representations regarding approval. PCM is not a bank and does not provide banking, deposit‑taking, securities brokerage, investment advisory, fiduciary, custody, wallet custody, or legal services. Prominence Bank alone determines whether to approve or decline an application and whether an account is opened. Any Account Opening Fee paid to PCM is a service fee for onboarding and compliance‑processing support; it is not a deposit with Prominence Bank and does not create or fund a bank account.

SCOPE OF PCM SERVICES

PCM services are limited to (i) assisting with completion of intake forms, (ii) coordinating collection of required documents, (iii) basic completeness checks (format/legibility), and (iv) transmitting the compiled application package to Prominence Bank. PCM does not provide advice, does not negotiate terms, does not handle client assets for investment or custody purposes, and does not represent that an application will be approved.`;

const STEP9_ATTESTATION_TEXT = `AGREED AND ATTESTED

By signing and submitting this Personal Bank Account Application, the Applicant(s) acknowledge(s), confirm(s), attest(s), represent(s), warrant(s), and irrevocably agree(s) to the following:

A. Mandatory Submission Requirements (Strict Compliance)

The Applicant(s) understand(s), acknowledge(s), and accept(s) that the Bank shall automatically reject, without substantive review, processing, or response, any application submitted without all mandatory items required by the Bank, including, without limitation:

• Full Personal Bank Account opening fee

• Valid proof of payment

• All required documentation, disclosures, and supporting materials specified in the application form

The Applicant(s) further acknowledge(s) that repeated submission of incomplete, deficient, inaccurate, or non-compliant applications may, at the Bank’s sole and absolute discretion, result in permanent disqualification from reapplying for any banking product or service.

B. Payment Instructions (Opening Fee)

The Applicant(s) acknowledge(s), understand(s), and accept(s) that payments made via KTT/TELEX are strictly prohibited and shall not be accepted under any circumstances for payment of the bank account opening fee.

Accepted methods of payment for the opening fee are strictly limited to the following:

• SWIFT international wire transfer

• Cryptocurrency transfer to the designated wallet address listed in the application form

The Applicant(s) further acknowledge(s) that the Application ID must be included in the payment reference field exactly as instructed by the Bank in order to ensure proper and timely allocation of funds. Incomplete, inaccurate, omitted, misdirected, or improperly referenced payments may delay processing and may result in rejection of the application, without liability to the Bank.

C. Account Opening Requirements

The Applicant(s) acknowledge(s), understand(s), and accept(s) that:

• A minimum balance of USD/EUR 5,000 must be maintained in the account at all times.

• Ongoing adherence to the Bank’s account policies, procedures, operational requirements, and compliance standards is required in order to maintain access to banking services.

• If the account balance falls below the minimum required level, the Bank may, in its sole discretion, restrict services, request corrective funding, apply internal controls, and/or place the account under compliance, risk, or administrative review until such deficiency has been remedied to the Bank’s satisfaction.

D. Finality of Account Type Selection; No Conversion or Reclassification After Opening

The Applicant(s) hereby acknowledge(s), confirm(s), represent(s), warrant(s), and irrevocably agree(s) that the account category selected in this Application is made solely at the Applicant’s own election, responsibility, and risk, and shall be deemed final for purposes of the submitted Application.

The Applicant(s) further acknowledge(s) and accept(s) that, once the Application has been submitted, approved by the Bank, and the account has been opened, activated, or established under the selected account category, such account category shall be final and may not thereafter be amended, converted, substituted, re-designated, reclassified, exchanged, or otherwise modified into any other account type, whether in whole or in part.

Without limitation, this restriction applies to any selection made by the Applicant, including, but not limited to, a Savings Account, Numbered Account, Cryptocurrency Account, Custody Account, or any other account class, structure, or product designation offered by the Bank from time to time. By way of example only, if an Applicant applies for and opens a Custody Account but later asserts that a Savings Account was intended, the Bank shall have no obligation and shall not be required to convert, redesignate, substitute, or otherwise treat such Custody Account as a Savings Account. The same principle shall apply equally to every other account category.

The Applicant(s) expressly acknowledge(s) that any error, misunderstanding, misinterpretation, oversight, administrative mistake, incorrect selection, or unintended election of account type made by the Applicant, whether arising before submission or discovered after account opening, shall remain solely the responsibility of the Applicant and shall not create any duty, liability, or obligation on the part of the Bank to alter, correct, or reclassify the account category after approval and opening.

Should the Applicant(s) subsequently wish(es) to obtain a different account type from the one originally selected and opened, the Applicant(s) must submit a new and separate application for the desired account category, together with all required forms, supporting documentation, due diligence information, compliance disclosures, and applicable fees then in force. Any such new application shall be subject to the Bank’s full onboarding, underwriting, compliance, risk, and approval procedures, and shall remain at all times subject to the Bank’s sole and absolute discretion.

For the avoidance of doubt, the submission, approval, and opening of one account type shall not confer upon the Applicant any right to demand conversion into another account type, nor shall it obligate the Bank to waive new application requirements, duplicate due diligence, revised fees, or any internal compliance, risk, legal, operational, or policy requirements applicable to the newly requested account category.

The Applicant(s) confirm(s) having read, understood, and expressly accepted this clause as a material and binding condition of the Application and of the banking relationship with the Bank.

E. Transaction Profile and Ongoing Due Diligence

The Applicant(s) acknowledge(s) and accept(s) that:

• Account activity must at all times reasonably align with the information declared in this application, including, without limitation, source of funds, source of wealth, countries involved, anticipated transactional activity, expected transaction volumes, and maximum transfer values.

• Any material deviation, inconsistency, anomaly, or change in activity profile may require additional verification and may, for compliance, security, legal, reputational, or operational reasons, be delayed, restricted, reviewed, declined, or otherwise subject to enhanced due diligence.

• The Applicant(s) agree(s) to provide such additional documentation, declarations, evidence, or clarifications as the Bank may request at any time in order to satisfy initial and ongoing AML/KYC, sanctions, fraud prevention, and internal risk-management requirements.

F. Accuracy and Authorization

The Applicant(s) hereby affirm(s), represent(s), warrant(s), and undertake(s) that:

• All information provided in this application is true, accurate, complete, current, and not misleading in any respect.

• The information is submitted for the purpose of establishing a service relationship with Prominence Bank (“the Bank”) under the Terms and Conditions disclosed prior to submission and accepted by the Applicant(s) upon signature and/or submission of this Application.

The Applicant(s) hereby authorize(s) the Bank, without further notice except where required by applicable law or the Bank’s governing framework, to:

• Verify all details provided in this application and in any supporting documentation.

• Conduct credit, fraud-prevention, identity, sanctions, adverse media, compliance, and risk checks, including AML/KYC screening and consultation with credit-risk information offices, databases, service providers, and entities affiliated with the Bank, where permitted.

• Request additional information or documentation at any time in connection with onboarding, account opening, risk review, or ongoing due diligence.

• Allocate, charge, and debit any applicable verification, compliance, administrative, legal, investigation, service-provider, and third-party processing costs to the Applicant’s account(s), where contractually permitted and/or required.

G. Account Retention, Record-Keeping, and Banking Relationship (ETMO Framework)

1) Bank-governed closure and retention.

Account status, retention, restriction, suspension, and any closure decision are governed exclusively by the Bank’s internal Administration, Compliance, Legal, Security, and Risk functions and may only be implemented following internal review, including, without limitation, risk analysis, AML/KYC review, sanctions screening, fraud review, cybersecurity controls, legal assessment, operational requirements, and/or record-retention considerations.

2) Account retention, record-keeping, and client-initiated closure restrictions.

The Applicant(s) acknowledge(s) that, due to the Bank’s regulatory obligations, auditability requirements, institutional record-retention duties, and long-term compliance commitments, accounts are not closed solely upon a client’s request. If the Applicant(s) wish(es) to terminate the relationship, the Bank may consider such request in accordance with its internal policies and procedures; however, the Bank retains the sole and absolute discretion to maintain the account in an administrative, dormant, restricted, archived, or other non-operational status where necessary to preserve records, satisfy retention obligations, complete compliance review, legal assessment, or orderly settlement.

3) ETMO diplomatic framework.

Account relationships are administered under the sovereign diplomatic framework of the Ecclesiastical and Temporal Missionary Order (ETMO), with reference to protections under the Vienna Convention on Diplomatic Relations (1961) and relevant bilateral and multilateral treaties, as applicable to the Bank’s institutional framework and operations.

4) Disengagement option (no closure).

If the Applicant(s) no longer wish(es) to use the account, the Applicant(s) may submit an outbound transfer instruction to another bank account of their choice, subject at all times to verification, authentication, applicable fees, and full compliance review, and provided that the account continues to satisfy the Bank’s minimum balance requirements both before and after such transfer. Any transfer instruction shall not constitute account closure and shall not limit the Bank’s right to maintain the account in an administrative, dormant, or restricted status where necessary for record retention, compliance review, legal assessment, or orderly settlement.

5) Bank discretion.

The Bank reserves the exclusive and unconditional right to restrict, suspend, terminate, place an account into administrative or restricted status, decline further activity, or close an account based upon internal risk analysis, compliance reviews, legal requirements, audit outcomes, due diligence findings, security concerns, or other risk-based considerations.

6) Administrative review standard.

Accounts maintaining a zero or negative balance and no activity for ninety (90) consecutive days may be subject to internal review and, where appropriate in the Bank’s discretion, may be administratively restricted, archived, or closed in accordance with applicable compliance, AML/KYC, sanctions, fraud screening, and regulatory requirements.

7) Binding acceptance.

This clause forms an integral part of the Account Application and shall become fully binding upon the Applicant(s) immediately upon signature and/or submission of this Application.

H. Compliance and Regulatory Framework

The Applicant(s) acknowledge(s), understand(s), and accept(s) that:

• Diplomatic Regulatory Framework and Governance. The Bank operates under a sovereign license within a diplomatic regulatory framework, and all accounts, services, products, operations, and client relationships are subject to the Bank’s internal governance, legal structure, compliance standards, risk-management framework, policies, and procedures. References to diplomatic recognition and the Vienna Convention on Diplomatic Relations are included solely to describe the Bank’s institutional framework and protections applicable to its operations.

• AML/KYC and Ongoing Obligations. The Applicant(s) must comply fully and promptly with all onboarding and ongoing AML/KYC, sanctions, source-of-funds, source-of-wealth, identity verification, and monitoring requirements, including the obligation to provide accurate information and supporting documentation whenever requested by the Bank.

• Internationally Aligned Standards. The Bank applies internationally aligned compliance and risk standards, including FATF-based AML controls, sanctions screening, and enhanced due diligence, and may apply monitoring, restrictions, manual review, account limitations, and other control measures whenever required for compliance, security, fraud prevention, legal protection, operational integrity, or institutional risk management.

• Account Retention / Non-Closure Policy. The Bank’s non-closure and retention policy is maintained to support continuity, traceability, auditability, and regulatory oversight in accordance with the Bank’s governing framework and internal policies. Any requests, objections, disputes, or claims relating to this policy shall be addressed through the procedures applicable under the Bank’s internal governance and regulatory framework.

Nothing in this section shall be construed to limit or exclude the Bank’s obligations to conduct AML/KYC reviews, sanctions screening, fraud prevention monitoring, enhanced due diligence, or other compliance measures when such actions are required by the Bank’s policies or applicable law.

I. Data Processing and Privacy

The Applicant(s) acknowledge(s), understand(s), and accept(s) that:

• Personal data and related information provided by the Applicant(s) are required for the purposes of evaluating, processing, administering, verifying, and maintaining this application and the requested banking services.

• The Bank is authorized to collect, process, store, transfer, verify, analyze, and disclose such data as required for compliance, risk management, legal obligations, and service delivery, and in accordance with the Bank’s privacy and data protection policies.

• Such data may be shared with third-party service providers, processors, correspondents, and regulators where required or permitted by law.

The Applicant(s) have certain rights to access and correct personal data under applicable law, subject to the Bank’s data protection policies and applicable legal requirements.

J. Additional Standard Banking Provisions (General)

The Applicant(s) further acknowledge(s), accept(s), and irrevocably agree(s) to the following provisions, each of which forms part of the binding service agreement with the Bank:

1. Bank discretion and service availability

The Bank may, at its sole and absolute discretion, decline, delay, restrict, suspend, refuse, reverse, or not process any application, account service, instruction, transaction, transfer, payment, or product feature where required for compliance, security, risk management, operational integrity, legal protection, incomplete information, unsatisfactory due diligence, sanctions concerns, or any other reason permitted under the Bank’s governing framework.

2. Transaction controls, holds, and third parties

The Bank may apply manual review, verification holds, enhanced due diligence, temporary restrictions, reserve requirements, and other internal controls whenever deemed necessary for AML/KYC, sanctions, fraud prevention, cybersecurity, legal review, operational risk, or institutional protection. The Applicant(s) further acknowledge(s) that payment routing may involve intermediaries, correspondents, custodians, networks, settlement institutions, and other third parties, and that the Bank shall not be liable for acts, omissions, delays, failures, or charges attributable to such third parties.

3. Fees, charges, and third-party costs

The Applicant(s) agree(s) that all Bank fees, service charges, intermediary or correspondent charges, network fees, blockchain fees, custody fees, FX conversion costs or spreads, investigation costs, legal costs, compliance costs, and third-party charges may be debited from the account and may reduce the net funds available.

4. Foreign exchange

Where currency conversion is required, authorized, or incidental to processing, the Applicant(s) authorize(s) the Bank to apply prevailing exchange rates and conversion spreads at the time of transaction execution, including any applicable fees.

5. Statements and reporting

The Bank’s records, statements, systems, logs, data extracts, and transaction histories constitute prima facie evidence of account activity and instructions unless proven otherwise. The Applicant(s) must review statements promptly and report any discrepancies within the timelines required by the Bank’s policies.

6. Instructions and authentication

The Bank may act on instructions received through approved channels, subject to verification and authentication. The Bank may withhold, reject, or reverse instructions that fail authentication or appear suspicious, fraudulent, or inconsistent with account controls.

7. Online banking and security responsibilities

The Applicant(s) are responsible for safeguarding login credentials, devices, authentication methods, and all access details. The Applicant(s) must notify the Bank immediately of suspected unauthorized access or security breaches.

8. Communications and notices

The Applicant(s) consent(s) to receive notices and disclosures electronically using their provided contact details. Notices are deemed delivered when sent to the contact details on file.

9. Ongoing disclosure obligation

The Applicant(s) must promptly notify the Bank of material changes to information or circumstances, including tax residency, identity, contact details, ownership, and source of funds.

10. Prohibited use

The account may not be used for unlawful, fraudulent, sanctionable, or high-risk activities, including but not limited to money laundering, terrorist financing, sanctions evasion, fraudulent schemes, or prohibited transactions.

11. Set-off and recovery

The Bank may debit, withhold, freeze, or set off funds to satisfy obligations, fees, liabilities, and costs owed to the Bank under applicable terms.

12. Indemnity

The Applicant(s) agree(s) to indemnify and hold harmless the Bank for losses arising from breaches of these terms, false statements, prohibited uses, and any unauthorized or non-compliant activities, except for losses directly caused by the Bank’s gross negligence or intentional misconduct.

13. Limitation of liability

To the fullest extent permitted by law, the Bank is not liable for indirect, consequential, punitive, or special damages, including loss of profit, opportunity, or reputation, except as required by applicable law.

14. Force majeure

The Bank is not liable for delays or failures caused by events beyond its reasonable control, including natural disasters, system failures, cyber events, governmental actions, or other force majeure events.

15. Severability and updates

If any provision is deemed invalid, the remainder remains in force. The Bank may update policies and terms, and continued use of services constitutes acceptance.

16. Acknowledgment and no-reliance

The Applicant(s) confirm(s) they have read and understood this Application and are not relying on representations outside these terms. This Application constitutes the entire agreement subject to the Bank’s governing framework.

17. Governing framework

This application is governed by the Bank’s internal governance, regulatory, and compliance framework, and the Applicant(s) acknowledge(s) that account approval is discretionary.

The Applicant(s) further confirm(s) that this clause is binding and that they have had the opportunity to seek independent advice prior to submission.
`;

export function StepRenderer() {
  const { currentStep, data, steps, isLoading, updateData } = useForm();
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [isDrawing, setIsDrawing] = useState(false);
  useEffect(() => {
    if (currentStep === 9 && canvasRef.current) {
      const canvas = canvasRef.current;
      const ctx = canvas.getContext('2d');
      if (ctx) {
        ctx.strokeStyle = "#0a192f";
        ctx.lineWidth = 2;
        ctx.lineCap = "round";
      }
    }
  }, [currentStep]);

  const startDrawing = (e: React.MouseEvent | React.TouchEvent) => {
    if (!canvasRef.current) return;
    setIsDrawing(true);
    const canvas = canvasRef.current;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const rect = canvas.getBoundingClientRect();
    const x = 'touches' in e ? e.touches[0].clientX - rect.left : (e as React.MouseEvent).clientX - rect.left;
    const y = 'touches' in e ? e.touches[0].clientY - rect.top : (e as React.MouseEvent).clientY - rect.top;
    ctx.beginPath();
    ctx.moveTo(x, y);
  };

  const stopDrawing = () => {
    setIsDrawing(false);
    if (canvasRef.current) {
      const dataUrl = canvasRef.current.toDataURL('image/jpeg', 0.9);
      updateData({ attestation: { ...data.attestation, signatureImage: dataUrl } });
    }
  };

  const draw = (e: React.MouseEvent | React.TouchEvent) => {
    if (!isDrawing || !canvasRef.current) return;
    const canvas = canvasRef.current;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const rect = canvas.getBoundingClientRect();
    const x = 'touches' in e ? e.touches[0].clientX - rect.left : (e as React.MouseEvent).clientX - rect.left;
    const y = 'touches' in e ? e.touches[0].clientY - rect.top : (e as React.MouseEvent).clientY - rect.top;

    ctx.lineTo(x, y);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(x, y);
  };

  const clearSignature = () => {
    if (canvasRef.current) {
      const ctx = canvasRef.current.getContext('2d');
      if (ctx) {
        ctx.clearRect(0, 0, canvasRef.current.width, canvasRef.current.height);
        updateData({ attestation: { ...data.attestation, signatureImage: "" } });
      }
    }
  };

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center py-24 gap-5">
        <Loader2 className="w-10 h-10 animate-spin text-[#c29d45]" />
        <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Secure System Synchronization...</p>
      </div>
    );
  }

  // Step 1: Account Selection
  const currentStepData = steps.find((step: any) => Number(step.order || step.id) === currentStep) || steps[currentStep - 1] || null;
  const activeStepData = currentStepData;

  if (currentStep === 1) {
    const heading = currentStepData?.title || 'Account Type Selection';
    const description = currentStepData?.description || 'Choose the type of account you wish to open';
    return (
      <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-500">
        <div className="space-y-1">
          <h2 className="text-2xl font-bold font-headline text-[#0a192f]">{heading}</h2>
          <p className="text-slate-400 text-[13px] font-normal">{description}</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {ACCOUNT_TYPES.map((account) => (
            <AccountCard
              key={account.id}
              account={account}
              isSelected={data.accountTypeId === account.id}
              onSelect={(id) => updateData({ accountTypeId: id })}
            />
          ))}
        </div>

        <div className="flex items-start gap-3 p-4 bg-[#f0f7ff] rounded-lg border border-[#d0e6ff]">
          <div className="bg-[#0066cc] p-1 rounded-sm shrink-0 mt-0.5">
            <Info className="w-3 h-3 text-white" />
          </div>
          <p className="text-[12px] font-normal leading-relaxed text-[#004080]">
            Savings Accounts, Custody Accounts and Numbered Accounts are approved for KTT transactions. Please consider this application in full using its GLOSSARY.
          </p>
        </div>
      </div>
    );
  }

  // Step 8: Document Uploads
  if (currentStep === 8) {
    const isPersonal = data.type === 'personal';
    const appId = data.applicationId;

    return (
      <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-500 font-body">
        <div className="space-y-2">
          <h2 className="text-xl font-bold text-[#0a192f] uppercase tracking-tight">PAYMENT</h2>
          <p className="text-slate-500 text-[12px] italic">Upload required documents</p>
        </div>

        <Accordion type="single" collapsible defaultValue="kyc-note">
          <AccordionItem value="kyc-note" className="border border-slate-200 rounded-lg overflow-hidden">
            <AccordionTrigger className="w-full text-left bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">KYC/AML DOCUMENTATION NOTE (Click to expand / view terms)</AccordionTrigger>
            <AccordionContent className="px-3 pb-3 pt-2 text-xs text-slate-600 leading-relaxed bg-white border-t border-slate-200 whitespace-pre-line">
              Please ensure all documents are clear and valid. PCM may assist with intake and document coordination and transmit the compiled package to Prominence Bank. Prominence Bank may request additional documentation or enhanced due diligence at any time. Incomplete or inconsistent information may delay processing or result in the application being declined.
            </AccordionContent>
          </AccordionItem>
        </Accordion>

        <div className="bg-white border border-slate-200 rounded-lg p-4 text-[12px] leading-relaxed text-slate-700">
          <h3 className="text-sm font-bold uppercase tracking-wide text-[#0a192f]">ACCOUNT OPENING FEE — PAYMENT INSTRUCTIONS</h3>
          <p className="mt-2 text-xs text-slate-600">Applicable to all new account types listed below.</p>
          <div className="mt-2 text-[11px] text-slate-600 space-y-2">
            <p>Account Opening Fee (Onboarding & Compliance Processing Fee)</p>
            <p>Payment of the Account Opening Fee does not guarantee approval or account opening.</p>
            <ul className="list-disc pl-5">
              <li>€25,000 – Euro Account</li>
              <li>$25,000 – USD Account</li>
              <li>€25,000 – Custody Account</li>
              <li>€25,000 – Cryptocurrency Account</li>
              <li>€50,000 – Numbered Account</li>
            </ul>
            <p className="font-semibold">REFUND POLICY (NO EXCEPTIONS)</p>
            <p>If the application is declined and no account is opened, the Account Opening Fee will be refunded in full by PCM (no PCM deductions). Please note intermediary banks, card processors, or blockchain networks may charge separate fees outside PCM’s control, which can affect the net amount received by the sender. Refunds are issued to the original sender (same payment route) within ten (10) business days after the application is formally declined in the Bank’s records.</p>
            <p>If the application is approved and an account is opened, the Account Opening Fee is deemed fully earned upon account opening and is non-refundable, as it covers completed onboarding, administrative coordination, and compliance processing services.</p>

            <p className="font-semibold">PAYMENT OPTION 1: INTERNATIONAL WIRE (SWIFT)</p>
            <p className="font-semibold">EURO (€) CURRENCY</p>
            <p>Bank Name: Wise Europe<br/>Bank Address: Rue du Trône 100, 3rd floor. Brussels. 1050. Belgium<br/>SWIFT Code: TRWIBEB1XXX<br/>Account Name: PROMINENCE CLIENT MANAGEMENT<br/>Account Number/IBAN: BE31905717979455<br/>Account Address: Rue du Trône 100, 3rd floor. Brussels. 1050. Belgium<br/>Payment Reference / Memo (REQUIRED): Application ID: {appId || 'XXXX'} | Onboarding and Compliance Processing Fee</p>
            <p className="font-semibold">USD ($) CURRENCY</p>
            <p>Bank Name: Wise US Inc.<br/>Bank Address: 108 W 13th St, Wilmington, DE, 19801, United States<br/>SWIFT Code: TRWIUS35XXX<br/>Account Name: PROMINENCE CLIENT MANAGEMENT<br/>Account Number: 205414015428310<br/>Account Address: 108 W 13th St, Wilmington, DE, 19801, United States<br/>Payment Reference / Memo (REQUIRED): Application ID: {appId || 'XXXX'} | Onboarding and Compliance Processing Fee</p>

            <p className="font-semibold">PAYMENT OPTION 2: CRYPTOCURRENCY (USDT TRC20)</p>
            <p>USDT Wallet Address (TRC20): TPYjSzK3BbZRZAVhBoRZcdyzKpQ9NN6S6Y</p>
            <p className="font-semibold">CRYPTOCURRENCY PAYMENT CONTROLS (USDT TRC20)</p>
            <p>Crypto is accepted solely as a payment method for the Account Opening Fee. PCM does not provide any virtual-asset exchange, brokerage, custody, wallet custody, or transfer service. To validate a crypto payment, you must provide (i) TXID/transaction hash, (ii) amount sent, (iii) sending wallet address, and (iv) timestamp and supporting screenshot (if available). Refunds (if due) are issued only to the originating wallet address after verification.</p>
            <p className="font-semibold">⚠️ IMPORTANT NOTICE:</p>
            <p>The Account Opening Fee must be paid via SWIFT international wire (Option 1), or USDT (Option 2). KTT / Telex are not accepted for this initial payment and will not be used to activate an account.</p>
          </div>
        </div>

        <div className="space-y-6">
          <div className="bg-amber-50 border border-amber-200 rounded-lg p-6">
            <h4 className="text-md font-bold text-amber-800 mb-3">📋 REQUIRED DOCUMENTS</h4>
            <ul className="text-sm text-amber-700 space-y-1">
              <li>• Valid ID/passport or equivalent document</li>
              <li>• Proof of payment for account opening fee</li>
              <li>• All documents must be uploaded before submission</li>
            </ul>
          </div>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label className="text-sm font-bold uppercase tracking-wider">Insert Full Color Photo of your Passport or ID *</Label>
              <div className="relative group">
                <Input
                  type="file"
                  className="h-24 opacity-0 absolute inset-0 z-10 cursor-pointer"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    updateData({
                      passportPhoto: file,
                      passportPhotoFile: file,
                    });
                  }}
                  accept=".jpg,.jpeg,.png,.pdf"
                  required
                />
                <div className="h-24 border-2 border-dashed rounded-sm flex flex-col items-center justify-center gap-2 bg-white group-hover:bg-slate-50 transition-all shadow-sm">
                  <Upload className="w-6 h-6 text-slate-400" />
                  <span className="text-[11px] font-normal text-slate-500">
                    {data.passportPhoto ? `${data.passportPhoto.name || 'File uploaded ✓'}` : "Upload passport/ID file (JPG, PNG, PDF)"}
                  </span>
                </div>
              </div>
            </div>
            <div className="space-y-2">
              <Label className="text-sm font-bold uppercase tracking-wider">Insert Full Color Photo of your Offshore Account Opening Fees Payment *</Label>
              <div className="relative group">
                <Input
                  type="file"
                  className="h-24 opacity-0 absolute inset-0 z-10 cursor-pointer"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    updateData({
                      paymentProof: file,
                      paymentProofFile: file,
                    });
                  }}
                  accept=".jpg,.jpeg,.png,.pdf"
                  required
                />
                <div className="h-24 border-2 border-dashed rounded-sm flex flex-col items-center justify-center gap-2 bg-white group-hover:bg-slate-50 transition-all shadow-sm">
                  <Upload className="w-6 h-6 text-slate-400" />
                  <span className="text-[11px] font-normal text-slate-500">
                    {data.paymentProof ? `${data.paymentProof.name || 'File uploaded ✓'}` : "Upload payment proof (JPG, PNG, PDF)"}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Step 9: Review & Attestation
  if (currentStep === 9) {
    const isPersonal = data.type === 'personal';
    return (
      <div className="space-y-8 animate-in fade-in duration-500 font-body">
        <div className="space-y-2">
          <h2 className="text-2xl font-bold font-headline text-[#0a192f]">AGREED AND ATTESTED</h2>
          <p className="text-slate-400 text-[13px] font-normal">Please review the mandatory legal framework before final submission.</p>
        </div>

        <div className="space-y-4">
          <div className="space-y-1">
            <h3 className="text-lg font-bold text-[#0a192f]">THIRD-PARTY ONBOARDING AND PAYMENT NOTICE</h3>
            <p className="text-slate-500 text-[12px]">Expand to review third-party onboarding and fee notices.</p>
          </div>
          <Accordion type="single" collapsible>
            <AccordionItem value="pcm-notice" className="border border-slate-200 rounded-lg overflow-hidden">
              <AccordionTrigger className="w-full text-left bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">THIRD-PARTY ONBOARDING AND PAYMENT NOTICE (click to expand)</AccordionTrigger>
              <AccordionContent className="px-4 pb-3 pt-2 text-xs text-slate-600 leading-relaxed whitespace-pre-line max-h-72 overflow-y-auto border-t border-slate-200 bg-white">
                {STEP9_PCM_NOTICE}
              </AccordionContent>
            </AccordionItem>
          </Accordion>

          <div className="space-y-1 pt-4">
            <h3 className="text-lg font-bold text-[#0a192f]">AGREED AND ATTESTED</h3>
            <p className="text-slate-500 text-[12px]">Expand to review the full attestation terms.</p>
          </div>
          <Accordion type="single" collapsible defaultValue="attested">
            <AccordionItem value="attested" className="border border-slate-200 rounded-lg overflow-hidden">
              <AccordionTrigger className="w-full text-left bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">AGREED AND ATTESTED (click to expand)</AccordionTrigger>
              <AccordionContent className="px-4 pb-3 pt-2 text-xs text-slate-600 leading-relaxed whitespace-pre-line max-h-80 overflow-y-auto border-t border-slate-200 bg-white">
                {STEP9_ATTESTATION_TEXT}
              </AccordionContent>
            </AccordionItem>
          </Accordion>

          <div className="bg-[#fff9e6] border border-[#fde68a] p-4 rounded-lg">
            <div className="flex items-start gap-3">
              <input
                type="checkbox"
                id="attest"
                className="mt-1 w-4 h-4 accent-[#0a192f]"
                checked={data.attestation?.agreedToTerms || false}
                onChange={(e) => updateData({ attestation: { ...data.attestation, agreedToTerms: e.target.checked } })}
              />
              <Label htmlFor="attest" className="text-[12px] font-semibold text-slate-700 cursor-pointer">
                I confirm I have read and agree to the terms of the application attestation.
              </Label>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <Input
              value={data.attestation?.signatureName || ''}
              onChange={(e) => updateData({ attestation: { ...data.attestation, signatureName: e.target.value } })}
              placeholder={isPersonal ? 'Full Name / Signature Name' : 'Name & Corporate Title'}
              className="h-10"
            />
            <Input
              value={data.attestation?.idNumber || ''}
              onChange={(e) => updateData({ attestation: { ...data.attestation, idNumber: e.target.value } })}
              placeholder="ID Number (Passport/ID)"
              className="h-10"
            />
            <Input
              type="date"
              value={data.attestation?.signatureDate || ''}
              onChange={(e) => updateData({ attestation: { ...data.attestation, signatureDate: e.target.value } })}
              className="h-10"
            />
          </div>

          <div className="space-y-2">
            <canvas
              ref={canvasRef}
              width={800}
              height={150}
              onMouseDown={startDrawing}
              onMouseMove={draw}
              onMouseUp={stopDrawing}
              onMouseLeave={stopDrawing}
              onTouchStart={startDrawing}
              onTouchMove={draw}
              onTouchEnd={stopDrawing}
              className="w-full h-[150px] border border-slate-300 rounded-sm"
            />
            <Button size="sm" variant="ghost" onClick={clearSignature}>Clear Signature</Button>
          </div>
        </div>
      </div>
    );
  }

  if (!activeStepData) {
    return <div>Step not found</div>;
  }

  // General step rendering for dynamic fields
  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-500">
      <div className="space-y-1">
        <h2 className="text-2xl font-bold font-headline text-[#0a192f]">{activeStepData.title}</h2>
        <p className="text-slate-400 text-[13px] font-normal">{activeStepData.description}</p>
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {activeStepData.fields.map((field: any) => (
          <div key={field.id} className={cn("space-y-2", field.width === 'half' ? '' : 'md:col-span-2')}>
            <Label htmlFor={field.name} className="text-sm font-bold uppercase tracking-wider flex items-center gap-2">
              {field.label}
              {field.required && <span className="text-red-500">*</span>}
            </Label>
            {field.type === 'text' && (
              <Input
                id={field.name}
                type="text"
                value={data[field.name] || ''}
                onChange={(e) => updateData({ [field.name]: e.target.value })}
                className="py-6 border-2 focus-visible:ring-accent"
                required={field.required}
              />
            )}
            {field.type === 'email' && (
              <Input
                id={field.name}
                type="email"
                value={data[field.name] || ''}
                onChange={(e) => updateData({ [field.name]: e.target.value })}
                className="py-6 border-2 focus-visible:ring-accent"
                required={field.required}
              />
            )}
            {field.type === 'number' && (
              <Input
                id={field.name}
                type="number"
                value={data[field.name] || ''}
                onChange={(e) => updateData({ [field.name]: e.target.value })}
                className="py-6 border-2 focus-visible:ring-accent"
                required={field.required}
              />
            )}
            {field.type === 'date' && (
              <Input
                id={field.name}
                type="date"
                value={data[field.name] || ''}
                onChange={(e) => updateData({ [field.name]: e.target.value })}
                className="py-6 border-2 focus-visible:ring-accent"
                required={field.required}
              />
            )}
            {field.type === 'select' && (
              <select
                id={field.name}
                value={data[field.name] || ''}
                onChange={(e) => updateData({ [field.name]: e.target.value })}
                className="py-6 border-2 focus-visible:ring-accent w-full"
                required={field.required}
              >
                <option value="">Select...</option>
                {field.options?.map((option: any) => (
                  <option key={option} value={option}>{option}</option>
                ))}
              </select>
            )}
            {field.type === 'textarea' && (
              <Textarea
                id={field.name}
                value={data[field.name] || ''}
                onChange={(e) => updateData({ [field.name]: e.target.value })}
                className="py-6 border-2 focus-visible:ring-accent"
                required={field.required}
              />
            )}
            {field.type === 'file' && (
              <div className="relative group">
                <Input
                  type="file"
                  className="h-24 opacity-0 absolute inset-0 z-10 cursor-pointer"
                  onChange={(e) => updateData({ [field.name]: e.target.files?.[0] })}
                  required={field.required}
                />
                <div className="h-24 border-2 border-dashed rounded-sm flex flex-col items-center justify-center gap-2 bg-white group-hover:bg-slate-50 transition-all shadow-sm">
                  <Upload className="w-6 h-6 text-slate-400" />
                  <span className="text-[11px] font-normal text-slate-500">
                    {data[field.name] ? `${data[field.name].name || 'File uploaded ✓'}` : "Click to select or drag and drop"}
                  </span>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
