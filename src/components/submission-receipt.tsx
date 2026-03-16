"use client";

import Image from "next/image";
import { Button } from "@/components/ui/button";
import { ExternalLink, Printer } from "lucide-react";

export type ReceiptData = {
  applicationId: string;
  applicationType: string;
  applicantName: string;
  companyName?: string;
  dateSubmitted: string;
  submissionMethod: string;
  status: string;
  email?: string;
};

export function SubmissionReceipt({
  data,
  onStartNew,
}: {
  data: ReceiptData;
  onStartNew: () => void;
}) {
  const print = () => {
    window.print();
  };

  return (
    <div className="min-h-screen bg-[#f5f7fa] px-4 py-10">
      <div className="max-w-4xl mx-auto bg-white shadow-lg border border-slate-200 rounded-xl overflow-hidden">
        <div className="flex flex-col md:flex-row items-start justify-between gap-6 px-6 py-6 border-b border-slate-200">
          <div className="text-sm leading-relaxed text-slate-700">
            <div className="flex flex-wrap gap-2">
              <span className="font-semibold">From:</span>
              <span>Prominence Bank Corp. &lt;account@prominencebank.com&gt;</span>
            </div>
            <div className="flex flex-wrap gap-2">
              <span className="font-semibold">Subject:</span>
              <span>Application Submission Receipt</span>
            </div>
            <div className="flex flex-wrap gap-2">
              <span className="font-semibold">Date:</span>
              <span>{data.dateSubmitted}</span>
            </div>
            {data.email ? (
              <div className="flex flex-wrap gap-2">
                <span className="font-semibold">To:</span>
                <span>{data.email}</span>
              </div>
            ) : null}
          </div>

          <div className="w-24 h-24 rounded-lg overflow-hidden flex items-center justify-center">
            <Image src="/Prominence Bank.png" alt="Prominence Bank" width={96} height={96} className="object-contain" />
          </div>
        </div>

          <div className="flex flex-col gap-3 items-stretch sm:flex-row sm:items-center no-print">
            <Button variant="outline" size="sm" onClick={print} className="gap-2">
              <Printer className="w-4 h-4" /> Print Receipt
            </Button>
            <Button variant="secondary" size="sm" onClick={onStartNew} className="gap-2">
              <ExternalLink className="w-4 h-4" /> New Application
            </Button>
          </div>
        </div>

        <div id="print-area" className="p-8 space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Application ID</h2>
              <p className="text-lg font-bold text-slate-800">{data.applicationId}</p>
            </div>
            <div>
              <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Status</h2>
              <p className="text-lg font-bold text-slate-800">{data.status}</p>
            </div>
            <div>
              <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Application Type</h2>
              <p className="text-lg font-bold text-slate-800">{data.applicationType}</p>
            </div>
            <div>
              <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Date Submitted</h2>
              <p className="text-lg font-bold text-slate-800">{data.dateSubmitted}</p>
            </div>
          </div>

          <div className="border border-slate-200 rounded-lg overflow-hidden">
            <table className="w-full text-sm">
              <tbody>
                <tr className="bg-slate-50">
                  <td className="px-4 py-3 font-semibold text-slate-600">Applicant Name</td>
                  <td className="px-4 py-3 text-slate-800">{data.applicantName}</td>
                </tr>
                {data.companyName ? (
                  <tr>
                    <td className="px-4 py-3 font-semibold text-slate-600">Company Name</td>
                    <td className="px-4 py-3 text-slate-800">{data.companyName}</td>
                  </tr>
                ) : null}
                {data.email ? (
                  <tr className="bg-slate-50">
                    <td className="px-4 py-3 font-semibold text-slate-600">Email</td>
                    <td className="px-4 py-3 text-slate-800">{data.email}</td>
                  </tr>
                ) : null}
                <tr>
                  <td className="px-4 py-3 font-semibold text-slate-600">Submission Method</td>
                  <td className="px-4 py-3 text-slate-800">{data.submissionMethod}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div className="rounded-lg bg-slate-50 border border-slate-200 p-6 text-sm leading-relaxed text-slate-700">
            <p className="font-semibold">Important:</p>
            <p className="mt-2">Please retain this receipt for your records. The Application ID above will be required for any communication with our onboarding department regarding this application.</p>
            <p className="mt-2">You will be notified by email once the review process is completed.</p>
            <p className="mt-2">For inquiries, please reference your Application ID.</p>
          </div>
        </div>
      <style jsx global>{`
        @media print {
          body, body * {
            visibility: hidden !important;
          }
          #print-area, #print-area * {
            visibility: visible !important;
          }
          #print-area {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            padding: 0 !important;
          }
          .no-print {
            display: none !important;
          }
        }
      `}</style>
    </div>
  );
}
