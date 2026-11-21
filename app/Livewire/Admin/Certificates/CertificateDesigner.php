<?php

namespace App\Livewire\Admin\Certificates;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Domain\Certificates\Models\CertificateTemplateVersion;
use App\Domain\Certificates\Services\CertificateTemplateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Certificate Designer')]
class CertificateDesigner extends Component
{
    use WithFileUploads;

    public ?string $templateId = null;

    public ?CertificateTemplate $template = null;

    public string $name = 'Custom Certificate';

    public string $description = '';

    public bool $isDefault = false;

    /** @var array<string, mixed> */
    public array $designData = [
        'canvas' => [
            'width' => 842,
            'height' => 595,
            'orientation' => 'landscape',
            'pageSize' => 'A4',
            'backgroundColor' => '#ffffff',
            'fontFamily' => 'Helvetica',
            'borderWidth' => 6,
            'borderColor' => '#1e3a8a',
            'borderStyle' => 'double',
            'borderInset' => 20,
            'innerBorderWidth' => 1,
            'innerBorderColor' => '#d97706',
            'innerBorderStyle' => 'solid',
            'innerBorderInset' => 8,
            'backgroundImage' => null,
        ],
        'elements' => [],
    ];

    public ?string $selectedElementId = null;

    public int $zoom = 100;

    public bool $showGrid = false;

    public bool $snapToGrid = false;

    public int $gridSize = 10;

    // Asset upload
    public $uploadedAsset = null;

    public string $assetType = 'image'; // image, logo, signature, background

    // Modals
    public bool $showVersionHistoryModal = false;

    public bool $showTemplatesModal = false;

    public bool $showPreviewModal = false;

    public bool $showNewTemplateModal = false;

    public string $newTemplateName = '';

    public string $newTemplatePreset = 'classic';

    public string $changeSummary = 'Updated template design';

    public ?string $previewPdfUrl = null;

    /** @var array<int, string> */
    public array $validationWarnings = [];

    public function mount(?string $template = null): void
    {
        $templateService = app(CertificateTemplateService::class);
        $user = Auth::user();
        if (! $user || ! $user->is_active || ($user->role !== 'admin' && ! $user->hasRole('admin'))) {
            abort(403, 'Unauthorized. Only administrators may access the Certificate Designer.');
        }

        $academy = Academy::current() ?? Academy::first();
        if (! $academy) {
            abort(404, 'Academy context not found.');
        }

        // Ensure starter templates exist if none present
        $templateService->ensureDefaultTemplatesExist($academy, $user);

        if ($template) {
            $this->template = CertificateTemplate::where('academy_id', $academy->id)
                ->where(function ($q) use ($template) {
                    $q->where('id', $template)
                        ->orWhere('slug', $template);
                })
                ->first();
        }

        if (! $this->template) {
            $this->template = $templateService->getDefaultTemplate($academy)
                ?? CertificateTemplate::where('academy_id', $academy->id)->first();
        }

        if ($this->template) {
            $this->loadTemplateData($this->template);
        }
    }

    public function loadTemplate(string $id): void
    {
        $template = CertificateTemplate::findOrFail($id);
        $this->authorizeAccess($template);
        $this->loadTemplateData($template);
        $this->showTemplatesModal = false;
        $this->dispatch('template-loaded');
    }

    protected function loadTemplateData(CertificateTemplate $template): void
    {
        $this->template = $template;
        $this->templateId = (string) $template->id;
        $this->name = $template->name;
        $this->description = $template->description ?? '';
        $this->isDefault = (bool) $template->is_default;
        $this->designData = $template->getActiveDesignData();
        $this->selectedElementId = null;
        $this->validationWarnings = [];
    }

    public function createNewTemplate(?CertificateTemplateService $templateService = null): void
    {
        $templateService ??= app(CertificateTemplateService::class);
        $academy = Academy::current() ?? Academy::first();
        $user = Auth::user();

        $name = trim($this->newTemplateName) !== '' ? trim($this->newTemplateName) : 'New Certificate Design';
        $presetDesign = $templateService->getPresetDesign($this->newTemplatePreset, $academy);

        $newTemplate = $templateService->createTemplate($academy, [
            'name' => $name,
            'is_default' => false,
            'design_data' => $presetDesign,
        ], $user);

        $this->showNewTemplateModal = false;
        $this->newTemplateName = '';
        $this->loadTemplateData($newTemplate);
        session()->flash('status', 'New certificate template created successfully.');
    }

    public function duplicateTemplate(?CertificateTemplateService $templateService = null): void
    {
        $templateService ??= app(CertificateTemplateService::class);
        if (! $this->template) {
            return;
        }

        $user = Auth::user();
        $copy = $templateService->duplicateTemplate($this->template, $user);
        $this->loadTemplateData($copy);
        session()->flash('status', "Duplicated template as '{$copy->name}'.");
    }

    public function saveDraft(?CertificateTemplateService $templateService = null): void
    {
        $templateService ??= app(CertificateTemplateService::class);
        if (! $this->template) {
            return;
        }

        $this->authorizeAccess($this->template);
        $user = Auth::user();

        $this->template->name = $this->name;
        $this->template->description = $this->description;
        $this->template->save();

        $templateService->saveDraft(
            $this->template,
            $this->designData,
            $user,
            $this->changeSummary ?: 'Saved draft changes'
        );

        session()->flash('status', 'Draft changes saved successfully.');
    }

    public function publishVersion(?CertificateTemplateService $templateService = null): void
    {
        $templateService ??= app(CertificateTemplateService::class);
        if (! $this->template) {
            return;
        }

        $this->authorizeAccess($this->template);
        $user = Auth::user();

        // Validate verification elements
        $warnings = $templateService->validateVerificationBindings($this->designData);
        $this->validationWarnings = $warnings;

        $this->template->name = $this->name;
        $this->template->description = $this->description;
        $this->template->save();

        $version = $templateService->publishVersion(
            $this->template,
            $this->designData,
            $user,
            $this->changeSummary ?: 'Published version'
        );

        $this->template->refresh();
        session()->flash('status', "Version {$version->version_number} published successfully to production.");
    }

    public function restoreVersion(string $versionId, ?CertificateTemplateService $templateService = null): void
    {
        $templateService ??= app(CertificateTemplateService::class);
        if (! $this->template) {
            return;
        }

        $version = CertificateTemplateVersion::findOrFail($versionId);
        $user = Auth::user();

        $templateService->restoreVersion($this->template, $version, $user);
        $this->loadTemplateData($this->template->fresh());
        $this->showVersionHistoryModal = false;
        session()->flash('status', "Restored template to version {$version->version_number}.");
    }

    public function setAsDefault(?CertificateTemplateService $templateService = null): void
    {
        $templateService ??= app(CertificateTemplateService::class);
        if (! $this->template) {
            return;
        }

        $templateService->setDefaultTemplate($this->template);
        $this->isDefault = true;
        session()->flash('status', "'{$this->template->name}' is now the default certificate template.");
    }

    public function loadPreset(string $preset, ?CertificateTemplateService $templateService = null): void
    {
        $templateService ??= app(CertificateTemplateService::class);
        $academy = Academy::current() ?? Academy::first();
        $this->designData = $templateService->getPresetDesign($preset, $academy);
        $this->selectedElementId = null;
        session()->flash('status', "Loaded '".ucfirst($preset)."' preset layout.");
    }

    // Element selection
    public function selectElement(?string $id): void
    {
        $this->selectedElementId = $id;
    }

    // Add elements
    public function addElement(string $type, array $customProps = []): void
    {
        $id = 'el-'.Str::random(8);
        $canvasWidth = (int) ($this->designData['canvas']['width'] ?? 842);
        $canvasHeight = (int) ($this->designData['canvas']['height'] ?? 595);

        $defaults = [
            'id' => $id,
            'type' => $type,
            'x' => 100,
            'y' => 100,
            'width' => 200,
            'height' => 50,
            'zIndex' => count($this->designData['elements']) + 2,
            'rotation' => 0,
            'opacity' => 1,
            'isLocked' => false,
            'fontFamily' => 'Helvetica',
            'fontSize' => 14,
            'fontWeight' => 'normal',
            'fontStyle' => 'normal',
            'textAlign' => 'center',
            'color' => '#1e293b',
            'lineHeight' => 1.2,
            'letterSpacing' => '0px',
            'textTransform' => 'none',
            'backgroundColor' => 'transparent',
            'borderColor' => 'transparent',
            'borderWidth' => 0,
            'borderStyle' => 'solid',
            'borderRadius' => 0,
            'padding' => 0,
            'content' => '',
        ];

        switch ($type) {
            case 'heading':
                $defaults['type'] = 'text';
                $defaults['content'] = 'CERTIFICATE OF COMPLETION';
                $defaults['fontSize'] = 28;
                $defaults['fontWeight'] = 'bold';
                $defaults['color'] = '#1e3a8a';
                $defaults['letterSpacing'] = '2px';
                $defaults['width'] = 600;
                $defaults['height'] = 45;
                $defaults['x'] = (int) (($canvasWidth - 600) / 2);
                $defaults['y'] = 80;
                break;

            case 'subtitle':
                $defaults['type'] = 'text';
                $defaults['content'] = 'THIS IS PROUDLY PRESENTED TO';
                $defaults['fontSize'] = 11;
                $defaults['fontWeight'] = 'bold';
                $defaults['color'] = '#64748b';
                $defaults['letterSpacing'] = '3px';
                $defaults['width'] = 400;
                $defaults['height'] = 25;
                $defaults['x'] = (int) (($canvasWidth - 400) / 2);
                $defaults['y'] = 135;
                break;

            case 'student_name':
                $defaults['type'] = 'text';
                $defaults['content'] = '{{student_name}}';
                $defaults['fontSize'] = 30;
                $defaults['fontWeight'] = 'bold';
                $defaults['color'] = '#0f172a';
                $defaults['width'] = 600;
                $defaults['height'] = 48;
                $defaults['x'] = (int) (($canvasWidth - 600) / 2);
                $defaults['y'] = 175;
                break;

            case 'course_title':
                $defaults['type'] = 'text';
                $defaults['content'] = '{{course_title}}';
                $defaults['fontSize'] = 22;
                $defaults['fontWeight'] = 'bold';
                $defaults['color'] = '#1e3a8a';
                $defaults['width'] = 650;
                $defaults['height'] = 40;
                $defaults['x'] = (int) (($canvasWidth - 650) / 2);
                $defaults['y'] = 265;
                break;

            case 'text':
                $defaults['content'] = 'Enter your custom text here';
                $defaults['width'] = 300;
                $defaults['height'] = 40;
                $defaults['x'] = (int) (($canvasWidth - 300) / 2);
                $defaults['y'] = 200;
                break;

            case 'line':
                $defaults['width'] = 240;
                $defaults['height'] = 10;
                $defaults['borderWidth'] = 1;
                $defaults['color'] = '#94a3b8';
                $defaults['borderStyle'] = 'solid';
                $defaults['x'] = (int) (($canvasWidth - 240) / 2);
                $defaults['y'] = 350;
                break;

            case 'rectangle':
                $defaults['width'] = 180;
                $defaults['height'] = 100;
                $defaults['backgroundColor'] = '#f8fafc';
                $defaults['borderWidth'] = 1;
                $defaults['borderColor'] = '#cbd5e1';
                $defaults['borderRadius'] = 4;
                $defaults['x'] = 100;
                $defaults['y'] = 100;
                break;

            case 'circle':
                $defaults['width'] = 80;
                $defaults['height'] = 80;
                $defaults['backgroundColor'] = '#fef3c7';
                $defaults['borderWidth'] = 2;
                $defaults['borderColor'] = '#d97706';
                $defaults['borderRadius'] = 40;
                $defaults['x'] = 80;
                $defaults['y'] = 80;
                break;

            case 'qr':
                $defaults['width'] = 80;
                $defaults['height'] = 80;
                $defaults['x'] = 75;
                $defaults['y'] = 450;
                $defaults['content'] = '{{verification_qr}}';
                break;

            case 'signature':
                $defaults['type'] = 'text';
                $defaults['content'] = "{{signatory_name}}\n{{signatory_designation}}";
                $defaults['width'] = 220;
                $defaults['height'] = 45;
                $defaults['fontSize'] = 11;
                $defaults['color'] = '#334155';
                $defaults['x'] = 540;
                $defaults['y'] = 460;
                break;

            case 'badge':
                $defaults['type'] = 'circle';
                $defaults['width'] = 70;
                $defaults['height'] = 70;
                $defaults['backgroundColor'] = '#fef3c7';
                $defaults['borderWidth'] = 3;
                $defaults['borderColor'] = '#d97706';
                $defaults['borderRadius'] = 35;
                $defaults['x'] = (int) (($canvasWidth - 70) / 2);
                $defaults['y'] = 330;
                break;
        }

        $element = array_merge($defaults, $customProps);
        $this->designData['elements'][] = $element;
        $this->selectedElementId = $id;
    }

    public function updateElementProperty(string $id, string $property, $value): void
    {
        foreach ($this->designData['elements'] as $idx => $el) {
            if ($el['id'] === $id) {
                $this->designData['elements'][$idx][$property] = $value;
                break;
            }
        }
    }

    public function updateCanvasProperty(string $property, $value): void
    {
        $this->designData['canvas'][$property] = $value;

        // If page size or orientation changed, adjust width and height
        if ($property === 'pageSize' || $property === 'orientation') {
            $pageSize = $this->designData['canvas']['pageSize'] ?? 'A4';
            $orientation = $this->designData['canvas']['orientation'] ?? 'landscape';

            if ($pageSize === 'A4') {
                $w = 842;
                $h = 595;
            } else { // Letter
                $w = 792;
                $h = 612;
            }

            if ($orientation === 'portrait') {
                $tmp = $w;
                $w = $h;
                $h = $tmp;
            }

            $this->designData['canvas']['width'] = $w;
            $this->designData['canvas']['height'] = $h;
        }
    }

    public function deleteElement(string $id): void
    {
        $this->designData['elements'] = array_values(
            array_filter($this->designData['elements'], fn ($el) => $el['id'] !== $id)
        );

        if ($this->selectedElementId === $id) {
            $this->selectedElementId = null;
        }
    }

    public function duplicateElement(string $id): void
    {
        foreach ($this->designData['elements'] as $el) {
            if ($el['id'] === $id) {
                $copy = $el;
                $copy['id'] = 'el-'.Str::random(8);
                $copy['x'] = (int) ($el['x'] ?? 0) + 15;
                $copy['y'] = (int) ($el['y'] ?? 0) + 15;
                $copy['zIndex'] = count($this->designData['elements']) + 2;
                $this->designData['elements'][] = $copy;
                $this->selectedElementId = $copy['id'];
                break;
            }
        }
    }

    public function reorderElement(string $id, string $action): void
    {
        $elements = $this->designData['elements'];
        $count = count($elements);

        foreach ($elements as $idx => $el) {
            if ($el['id'] === $id) {
                $curZ = (int) ($el['zIndex'] ?? 2);
                if ($action === 'bring_to_front') {
                    $maxZ = max(array_column($elements, 'zIndex')) ?: 2;
                    $elements[$idx]['zIndex'] = $maxZ + 1;
                } elseif ($action === 'send_to_back') {
                    $minZ = min(array_column($elements, 'zIndex')) ?: 2;
                    $elements[$idx]['zIndex'] = max(1, $minZ - 1);
                } elseif ($action === 'move_up') {
                    $elements[$idx]['zIndex'] = $curZ + 1;
                } elseif ($action === 'move_down') {
                    $elements[$idx]['zIndex'] = max(1, $curZ - 1);
                }
                break;
            }
        }

        $this->designData['elements'] = $elements;
    }

    public function toggleLockElement(string $id): void
    {
        foreach ($this->designData['elements'] as $idx => $el) {
            if ($el['id'] === $id) {
                $this->designData['elements'][$idx]['isLocked'] = ! ($el['isLocked'] ?? false);
                break;
            }
        }
    }

    public function alignElement(string $id, string $alignment): void
    {
        $canvasWidth = (int) ($this->designData['canvas']['width'] ?? 842);
        $canvasHeight = (int) ($this->designData['canvas']['height'] ?? 595);

        foreach ($this->designData['elements'] as $idx => $el) {
            if ($el['id'] === $id) {
                $w = (int) ($el['width'] ?? 100);
                $h = (int) ($el['height'] ?? 50);

                switch ($alignment) {
                    case 'center':
                        $this->designData['elements'][$idx]['x'] = (int) (($canvasWidth - $w) / 2);
                        break;
                    case 'left':
                        $this->designData['elements'][$idx]['x'] = 30;
                        break;
                    case 'right':
                        $this->designData['elements'][$idx]['x'] = $canvasWidth - $w - 30;
                        break;
                    case 'middle':
                        $this->designData['elements'][$idx]['y'] = (int) (($canvasHeight - $h) / 2);
                        break;
                    case 'top':
                        $this->designData['elements'][$idx]['y'] = 30;
                        break;
                    case 'bottom':
                        $this->designData['elements'][$idx]['y'] = $canvasHeight - $h - 30;
                        break;
                }
                break;
            }
        }
    }

    public function uploadAsset(?CertificateTemplateService $templateService = null): void
    {
        $this->validate([
            'uploadedAsset' => 'required|image|max:3072', // 3MB max
        ]);

        $academy = Academy::current() ?? Academy::first();
        $path = $this->uploadedAsset->store('certificates/assets', 'public');
        $url = asset('storage/'.$path);

        if ($this->assetType === 'background') {
            $this->designData['canvas']['backgroundImage'] = $url;
        } else {
            $this->addElement('image', [
                'type' => $this->assetType,
                'src' => $url,
                'width' => 120,
                'height' => 60,
            ]);
        }

        $this->uploadedAsset = null;
        session()->flash('status', 'Asset uploaded successfully.');
    }

    public function openPreviewModal(): void
    {
        if ($this->template) {
            $this->previewPdfUrl = route('admin.certificates.designer.preview-pdf', ['template' => $this->template->id]);
        }
        $this->showPreviewModal = true;
    }

    public function getSelectedElementProperty(): ?array
    {
        if (! $this->selectedElementId) {
            return null;
        }

        foreach ($this->designData['elements'] as $el) {
            if ($el['id'] === $this->selectedElementId) {
                return $el;
            }
        }

        return null;
    }

    protected function authorizeAccess(CertificateTemplate $template): void
    {
        $user = Auth::user();
        if (! $user || ! $user->is_active || ($user->role !== 'admin' && ! $user->hasRole('admin'))) {
            abort(403, 'Unauthorized.');
        }
    }

    public function render()
    {
        $academy = Academy::current() ?? Academy::first();
        $templates = CertificateTemplate::where('academy_id', $academy?->id)
            ->where('is_archived', false)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $versions = $this->template
            ? $this->template->versions()->with('author')->latest('version_number')->get()
            : collect();

        $sampleData = app(CertificateTemplateService::class)->getSamplePreviewData($academy);

        return view('livewire.admin.certificates.certificate-designer', [
            'templates' => $templates,
            'versions' => $versions,
            'selectedElement' => $this->selectedElement,
            'sampleData' => $sampleData,
            'placeholders' => CertificateTemplateService::SUPPORTED_PLACEHOLDERS,
        ]);
    }
}
