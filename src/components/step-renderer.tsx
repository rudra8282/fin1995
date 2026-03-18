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
• The Applicant(s) understand(s), acknowledge(s), and accept(s) that the Bank shall automatically reject, without substantive review, processing, or response, any application submitted without all mandatory items required by the Bank, including, without limitation:
  - Full Bank Account opening fee
  - Valid proof of payment
  - All required documentation, disclosures, and supporting materials specified in the application form
• Repeated submission of incomplete, deficient, inaccurate, or non-compliant applications may result in permanent disqualification from reapplying for any banking product or service.

B. Payment Instructions (Opening Fee)
• Payments by KTT/TELEX are strictly prohibited.
• Accepted methods: SWIFT international wire transfer; Cryptocurrency transfer to the designated wallet address.
• Application ID must be included in payment reference.

C. Account Opening Requirements
• Minimum balance required, ongoing policy compliance, monitoring, internal controls and risk review.

D. Finality of Account Type Selection
• Account type selected is final. No conversion/reclassification after opening.
• New application required for different account type.

E. Transaction Profile and Ongoing Due Diligence
• Account activity must align with declared profile.
• Material deviations may require additional verification.

F. Accuracy and Authorization
• Applicant(s) affirm all information is true, accurate, complete, current, and not misleading.
• Bank may verify details, perform compliance checks, and collect applicable costs.

G. Account Retention, Record-Keeping, and Banking Relationship (ETMO Framework)
• Account closure and retention are governed by Bank compliance/legal framework.
• Accounts may be retained in administrative status for record retention and regulatory reasons.

H. Compliance and Regulatory Framework
• Applicant(s) agree(s) to comply with AML/KYC, sanctions, and risk requirements.
• Bank operates under sovereign diplomatic framework and international compliance standards.

I. Data Processing and Privacy
• Applicant(s) consent(s) to data processing for application evaluation, onboarding, and compliance.

J. Additional Standard Banking Provisions
• Bank may restrict/suspend/terminate/refuse services under policies and regulatory requirements.

1. Applicant(s) confirm(s) they have carefully read and understood this Application and are not relying upon any representation other than stated by the Bank.
2. Applicant(s) authorize(s) the Bank to conduct all necessary due diligence and compliance checks.
3. Applicant(s) agree(s) to provide additional documentation or clarifications if requested.
4. Applicant(s) acknowledge(s) that the Bank may verify source of funds, wealth, activity, and may request documentation.
5. Applicant(s) accept(s) that payment instructions, fees, and non-refundable terms apply as disclosed.
6. Applicant(s) acknowledge(s) all declarations are made under penalty of perjury and applicable law.
7. Applicant(s) agree(s) that Bank may share information with authorized regulators and service providers as required by law.
8. Applicant(s) confirm(s) that any omissions, inaccuracies, or falsifications may lead to application rejection and/or account closure.
9. Applicant(s) understand(s) the Bank’s rights to refuse transactions deemed suspicious or non-compliant.
10. Applicant(s) waive(s) claims arising from misunderstanding if the Bank provides clear written instructions and disclosures.
11. Applicant(s) acknowledge(s) these provisions shall survive account opening and termination.

This attestation text is included to reflect the full legal requirements, bullet points and obligations intended for Step 9 in the application process.`;

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
    setIsDrawing(true);
    draw(e);
  };

  const stopDrawing = () => {
    setIsDrawing(false);
    if (canvasRef.current) {
      const dataUrl = canvasRef.current.toDataURL();
      updateData({ attestation: { ...data.attestation, signatureImage: dataUrl } });
    }
  };

  const draw = (e: React.MouseEvent | React.TouchEvent) => {
    if (!isDrawing || !canvasRef.current) return;
    const canvas = canvasRef.current;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const rect = canvas.getBoundingClientRect();
    const x = ('touches' in e) ? e.touches[0].clientX - rect.left : (e as React.MouseEvent).clientX - rect.left;
    const y = ('touches' in e) ? e.touches[0].clientY - rect.top : (e as React.MouseEvent).clientY - rect.top;

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

        {activeStepData?.description && (
          <div className="bg-white border border-slate-200 rounded-lg p-6 text-[11px] leading-relaxed text-slate-600">
            <div className="whitespace-pre-line">{activeStepData.description}</div>
          </div>
        )}

        <div className="space-y-6">
          <div className="bg-amber-50 border border-amber-200 rounded-lg p-6">
            <h4 className="text-md font-bold text-amber-800 mb-3">📋 REQUIRED DOCUMENTS</h4>
            <ul className="text-sm text-amber-700 space-y-1">
              <li>• Passport/ID photo (full color, clear image)</li>
              <li>• Proof of payment for account opening fee</li>
              <li>• All documents must be uploaded before submission</li>
            </ul>
          </div>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label className="text-sm font-bold uppercase tracking-wider">Insert Full Color Photo of your Passport Here *</Label>
              <div className="relative group">
                <Input
                  type="file"
                  className="h-24 opacity-0 absolute inset-0 z-10 cursor-pointer"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    updateData({
                      passportPhoto: file,
                      mainDocumentFile: file,
                    });
                  }}
                  accept=".jpg,.jpeg,.png,.pdf"
                  required
                />
                <div className="h-24 border-2 border-dashed rounded-sm flex flex-col items-center justify-center gap-2 bg-white group-hover:bg-slate-50 transition-all shadow-sm">
                  <Upload className="w-6 h-6 text-slate-400" />
                  <span className="text-[11px] font-normal text-slate-500">
                    {data.passportPhoto ? `${data.passportPhoto.name || 'File uploaded ✓'}` : "Upload passport photo (JPG, PNG, PDF)"}
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

        <div className="space-y-6">
          <Accordion type="single" collapsible>
            <AccordionItem value="pcm-notice">
              <AccordionTrigger className="text-base font-bold text-[#0a192f]">THIRD-PARTY ONBOARDING AND PAYMENT NOTICE <span className="text-xs font-normal text-slate-500">(Click to expand / view terms)</span></AccordionTrigger>
              <AccordionContent className="text-[11px] text-slate-600 border border-slate-200 rounded-b-lg bg-slate-50 p-4">
                <p className="mb-2 font-semibold">This application may be supported by Prominence Client Management / Prominence Account Management (“PCM”), a separate legal entity acting as an independent introducer and providing administrative onboarding coordination only (intake support, document collection coordination, and application-package transmission).</p>
                <p className="mb-2">PCM is not authorized to bind Prominence Bank or make representations regarding approval. PCM is not a bank and does not provide banking, deposit-taking, securities brokerage, investment advisory, fiduciary, custody, wallet custody, or legal services. Prominence Bank alone determines whether to approve or decline an application and whether an account is opened. Any Account Opening Fee paid to PCM is a service fee for onboarding and compliance-processing support; it is not a deposit with Prominence Bank and does not create or fund a bank account.</p>
                <p className="font-semibold">SCOPE OF PCM SERVICES</p>
                <ul className="list-disc pl-5 text-[11px] space-y-1">
                  <li>Assisting with completion of intake forms</li>
                  <li>Coordinating collection of required documents</li>
                  <li>Basic completeness checks (format/legibility)</li>
                  <li>Transmitting the compiled application package to Prominence Bank</li>
                </ul>
                <p className="mt-2">PCM does not provide advice, does not negotiate terms, does not handle client assets for investment or custody purposes, and does not represent that an application will be approved.</p>
              </AccordionContent>
            </AccordionItem>
          </Accordion>

          <div className="bg-white border rounded-lg p-6 max-h-[400px] overflow-y-auto text-[11px] leading-relaxed text-slate-600 whitespace-pre-wrap shadow-inner">
            {STEP9_ATTESTATION_TEXT}
          </div>

            <section className="space-y-2">
              <h4 className="font-bold text-primary uppercase tracking-tight">H. Compliance and Regulatory Framework</h4>
              <p className="font-normal">The Applicant(s) agree(s) to comply with all onboarding and ongoing AML/KYC, sanctions, and risk requirements, and acknowledges that the Bank operates under a sovereign diplomatic framework and international compliance standards.</p>
            </section>

            <section className="space-y-2">
              <h4 className="font-bold text-primary uppercase tracking-tight">I. Data Processing and Privacy</h4>
              <p className="font-normal">The Applicant(s) consent(s) to data processing for application evaluation, onboarding, and compliance as described, including storage and transfer to authorized processors.</p>
            </section>

            <section className="space-y-2">
              <h4 className="font-bold text-primary uppercase tracking-tight">J. Additional Standard Banking Provisions</h4>
              <p className="font-normal">The Bank reserves the right to restrict, suspend, terminate, or refuse services in accordance with internal policies and regulatory requirements. The Applicant(s) acknowledge(s) that this is a binding part of the application.</p>
            </section>

            <section className="space-y-2">
               <h4 className="font-bold text-primary uppercase tracking-tight">16. Waiver of Claims based on Misunderstanding</h4>
               <p className="font-normal">The Applicant(s) confirm(s) that they have carefully read and understood this Application and are not relying upon any representation other than those expressly set forth by the Bank in writing.</p>
            </section>
          </div>

          <div className="bg-[#fff9e6] border border-[#fde68a] p-6 rounded-lg">
             <div className="flex items-start gap-4">
                <input
                  type="checkbox"
                  id="attest"
                  className="mt-1 w-4 h-4 accent-[#0a192f] shrink-0"
                  checked={data.attestation?.agreedToTerms}
                  onChange={(e) => updateData({attestation: { ...data.attestation, agreedToTerms: e.target.checked}})}
                />
                <Label htmlFor="attest" className="text-[12px] font-bold leading-relaxed text-[#856404] cursor-pointer">
                  I confirm that I have read and agree to the "AGREED AND ATTESTED" section, including the payment/refund terms and the Bank's account retention and record-keeping provisions.
                </Label>
             </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
            <div className="space-y-2">
              <Label className="text-[11px] font-bold uppercase tracking-widest text-primary">{isPersonal ? 'Full Name / Signature Name' : 'Name & Title'} *</Label>
              <Input
                value={data.attestation?.signatureName}
                onChange={(e) => updateData({attestation: { ...data.attestation, signatureName: e.target.value}})}
                placeholder={isPersonal ? "Type your official name" : "Name & Corporate Title"}
                className="h-12 rounded-sm font-normal bg-white"
              />
            </div>
            <div className="space-y-2">
              <Label className="text-[11px] font-bold uppercase tracking-widest text-primary">ID Number (Passport/ID) *</Label>
              <Input
                value={data.attestation?.idNumber}
                onChange={(e) => updateData({attestation: { ...data.attestation, idNumber: e.target.value}})}
                placeholder="Enter document number"
                className="h-12 rounded-sm font-normal bg-white"
              />
            </div>
            <div className="space-y-2 md:col-span-2">
              <Label className="text-[11px] font-bold uppercase tracking-widest text-primary">Signature Date *</Label>
              <Input
                type="date"
                value={data.attestation?.signatureDate}
                onChange={(e) => updateData({attestation: { ...data.attestation, signatureDate: e.target.value}})}
                className="h-12 rounded-sm font-normal bg-white"
              />
            </div>
          </div>

          {/* Drawing Signature Section */}
          <div className="space-y-4 pt-6 border-t">
            <div className="flex items-center justify-between">
              <Label className="text-[11px] font-bold uppercase tracking-widest text-primary">Signature Pad (Draw your signature below) *</Label>
              <Button
                variant="ghost"
                size="sm"
                onClick={clearSignature}
                className="text-[10px] uppercase font-bold text-red-500 hover:text-red-700 hover:bg-red-50"
              >
                <Eraser className="w-3 h-3 mr-1" /> Clear Pad
              </Button>
            </div>
            <div className="border-2 border-dashed border-slate-200 rounded-lg bg-white overflow-hidden">
              <canvas
                ref={canvasRef}
                width={800}
                height={200}
                onMouseDown={startDrawing}
                onMouseMove={draw}
                onMouseUp={stopDrawing}
                onMouseLeave={stopDrawing}
                onTouchStart={startDrawing}
                onTouchMove={draw}
                onTouchEnd={stopDrawing}
                className="w-full h-[200px] cursor-crosshair touch-none"
              />
            </div>
            <p className="text-[10px] text-slate-400 italic">Please draw your signature using your mouse or touch screen. This will be stored as an encrypted proof of attestation.</p>
          </div>

          {!isPersonal && (
            <div className="p-4 bg-slate-50 border rounded-lg space-y-4">
               <p className="text-[10px] font-bold text-primary uppercase tracking-wider">⚠️ IMPORTANT NOTICE – INCOMPLETE OR NON-COMPLIANT BUSINESS ACCOUNT APPLICATIONS WILL BE REJECTED</p>
               <div className="space-y-2">
                <p className="text-[11px] font-bold text-primary">Why Choose Prominence Bank for Your Business?</p>
                <ul className="text-[10px] text-slate-500 list-disc pl-5 space-y-1">
                  <li>Secure, multi-currency banking solutions</li>
                  <li>Dedicated relationship management</li>
                  <li>Confidential and compliant offshore banking services</li>
                  <li>Global access to funds and financial tools tailored to your operations</li>
                </ul>
               </div>
            </div>
          )}
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
        {activeStepData.fields.map((field) => (
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
                {field.options?.map((option) => (
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
