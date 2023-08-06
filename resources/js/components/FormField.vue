<template>
    <DefaultField
        :field="currentField"
        :label-for="labelFor"
        :errors="errors"
        :show-help-text="!isReadonly && showHelpText"
        :full-width-content="fullWidthContent"
    >
        <template #field>
            <!-- Existing Image -->
            <div class="space-y-4">
                <div
                    v-if="hasValue || previewFile"
                    class="grid grid-cols-4 gap-x-6 gap-y-2"
                    ref="imageList"
                >
                    <div class="single_image" v-if="!field.multiple">
                    <FilePreviewBlock
                        v-if="previewFile && !field.multiple"
                        :file="previewFile"
                        :removable="false"
                        :rounded="field.rounded"
                        :dusk="field.attribute + '-delete-link'"
                    />
                    </div>


                    <div v-if="field.multiple" v-for="(path, index) in JSON.parse(currentField.value)" :key="path">
                        <div class="h-full flex items-start justify-center">
                            <div class="relative w-full">
                                <!-- Remove Button -->
                                <RemoveButton
                                    class="absolute z-20 top-[-10px] right-[-9px]"
                                    @click.stop="confirmRemoval(index)"
                                    v-tooltip="__('Remove')"
                                    :dusk="currentField.attribute + '-delete-link'"
                                />

                                <div
                                    class="bg-gray-50 dark:bg-gray-700 relative aspect-square flex items-center justify-center border-2 border-gray-200 dark:border-gray-700 overflow-hidden rounded-lg"
                                >

                                    <!-- Image Preview -->
                                    <img
                                        :src="domain + path"
                                        class="aspect-square object-scale-down"
                                    />
                                </div>

                                <!-- File Information -->
                                <p :title="path" class="multi_title font-semibold text-xs mt-1">{{ path }}</p>
                            </div>
                        </div>
                    </div>

                </div>


                <!-- DropZone -->
                <DropZone
                    v-if="!field.multiple"
                    @change="handleFileChange"
                    @file-changed="handleFileChange"
                    :files="files"
                    @file-removed="removeFile"
                    :rounded="field.rounded"
                    :accepted-types="field.acceptedTypes"
                    :disabled="file?.processing"
                    :dusk="field.attribute + '-delete-link'"
                    :input-dusk="field.attribute"
                />
                <DropZone
                    v-else
                    @change="handleFileChange"
                    @file-changed="handleFileChange"
                    :files="images"
                    @file-removed="removeFile"
                    :rounded="field.rounded"
                    :accepted-types="field.acceptedTypes"
                    :disabled="file?.processing"
                    :dusk="field.attribute + '-delete-link'"
                    :input-dusk="field.attribute"
                    multiple
                    :ref="dropzone"
                />
            </div>
        </template>
    </DefaultField>
</template>

<script>
import {DependentFormField, Errors, HandlesValidationErrors} from 'laravel-nova'
import Sortable from 'sortablejs';

function createFile(file) {
    return {
        name: file.name,
        extension: file.name.split('.').pop(),
        type: file.type,
        originalFile: file,
    }
}

export default {
    emits: ['file-deleted', 'removed'],

    props: [
        'resourceId',
        'relatedResourceName',
        'relatedResourceId',
        'viaRelationship',
    ],

    mixins: [HandlesValidationErrors, DependentFormField],

    data: () => ({
        previewFile: null,
        file: null,
        images: [],
        removeModalOpen: false,
        missing: false,
        imageForPreview: null,
        domain: null,
        deleted: false,
        uploadErrors: new Errors(),
        uploadProgress: 0,
        startedDrag: false,
        uploadModalShown: false,
    }),

    async mounted() {
        this.getFullDomainWithProtocol()
        this.preparePreviewImage()

        this.field.fill = formData => {
            let attribute = this.field.attribute

            if (this.field.multiple) {

                const fieldJSON = () => {
                    try {
                        JSON.parse(this.currentField.value)
                        return true;
                    } catch (e) {
                        return false;
                    }
                };

                if (this.currentField.value && fieldJSON()) {
                    // Parse existing images
                    const existingImages = JSON.parse(this.currentField.value);
                    // Create a string with a list of existing image URLs separated by commas
                    const existingImagesString = existingImages.join(',');
                    // Append the existing images to the FormData as a comma-separated string
                    formData.append(attribute + '_existing', existingImagesString);
                }


                if (this.images) {
                    this.images.forEach(image => {
                        formData.append(attribute + '[]', image.originalFile, image.name)
                    })
                }
            } else {
                if (this.file) {
                    formData.append(attribute, this.file.originalFile, this.file.name)
                }
            }
        }

        setTimeout(() => {
            this.$nextTick(() => {
                const el = this.$refs.imageList;
                if (el) {
                    Sortable.create(el);
                    el.addEventListener('update', this.handleSortableUpdate);
                }
            });
        }, 500);
    },

    methods: {

        getFullDomainWithProtocol() {
            const protocol = window.location.protocol;
            const hostname = window.location.hostname;
            const port = window.location.port ? ':' + window.location.port : '';

            this.domain = `${protocol}//${hostname}${port}`;
        },


        handleSortableUpdate(event) {
            const newOrder = Object.values(event.target.children).map((item) => item.innerText);

            this.currentField.value = JSON.stringify(newOrder);
        },

        preparePreviewImage() {
            if (this.hasValue) {
                this.fetchPreviewImage()
            }
        },

        convertWebPToPNG(blob) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.src = URL.createObjectURL(blob);
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    ctx.drawImage(img, 0, 0);
                    const imageDataURL = canvas.toDataURL('image/png');
                    resolve(imageDataURL);
                };
                img.onerror = (error) => {
                    reject(error);
                };
            });
        },

        async fetchPreviewImage() {
            if (this.field.multiple) {
                let jsonImage = JSON.parse(this.currentField.value);
                this.imageForPreview = jsonImage[0]
            } else {
                this.imageForPreview = this.currentField.value
            }

            let response = await fetch(this.imageForPreview)
            let data = await response.blob()

            fetch(this.imageForPreview)
                .then(response => response.blob())
                .then(data => {
                    if (data.type === 'image/webp') {
                        return this.convertWebPToPNG(data)
                            .then(imageDataURL => fetch(imageDataURL))
                            .then(response => response.blob())
                            .then(imageData => {
                                const convertedFile = new File([imageData], this.imageForPreview, { type: imageData.type });
                                this.previewFile = createFile(convertedFile);
                            });
                    } else {
                        this.previewFile = createFile(
                            new File([data], this.imageForPreview, {type: data.type})
                        )
                    }
                })
                .catch(error => {
                    console.error('Error fetching preview image:', error);
                });
        },

        handleFileChange(newFiles) {
            if (this.field.multiple) {
                this.images = this.images.concat(Array.from(newFiles, createFile));
            } else {
                this.file = createFile(newFiles[0]);
            }
        },

        removeFile(index) {
            if (this.field.multiple) {
                this.currentField.value.splice(index, 1);
            } else {
                this.currentField.value = null;
            }
        },

        confirmRemoval(index) {
            let images_delete = JSON.parse(this.currentField.value);
            images_delete.splice(index, 1);
            this.currentField.value = JSON.stringify(images_delete);
        },

        closeRemoveModal() {
            this.removeModalOpen = false
        },

        async removeUploadedFile() {
            this.uploadErrors = new Errors()

            const {
                resourceName,
                resourceId,
                relatedResourceName,
                relatedResourceId,
                viaRelationship,
            } = this
            const attribute = this.field.attribute

            const uri =
                this.viaRelationship &&
                this.relatedResourceName &&
                this.relatedResourceId
                    ? `/nova-api/${resourceName}/${resourceId}/${relatedResourceName}/${relatedResourceId}/field/${attribute}?viaRelationship=${viaRelationship}`
                    : `/nova-api/${resourceName}/${resourceId}/field/${attribute}`

            try {
                await Nova.request().delete(uri)
                this.closeRemoveModal()
                this.deleted = true
                this.$emit('file-deleted')
                Nova.success(this.__('The image was deleted!'))
            } catch (error) {
                this.closeRemoveModal()

                if (error.response?.status === 422) {
                    this.uploadErrors = new Errors(error.response.data.errors)
                }
            }
        },
    }
    ,

    computed: {
        files() {
            return this.field.multiple ? this.images : this.file ? [this.file] : [];
        },

        /**
         * Determine if the field has an upload error.
         */
        hasError() {
            return this.uploadErrors.has(this.fieldAttribute)
        },

        /**
         * Return the first error for the field.
         */
        firstError() {
            if (this.hasError) {
                return this.uploadErrors.first(this.fieldAttribute)
            }
        },

        /**
         * The ID attribute to use for the file field.
         */
        idAttr() {
            return this.labelFor
        },

        /**
         * The label attribute to use for the file field.
         */
        labelFor() {
            let name = this.resourceName

            if (this.relatedResourceName) {
                name += '-' + this.relatedResourceName
            }

            return `imagic-${name}-${this.field.attribute}`
        },

        /**
         * Determine whether the field has a value.
         */
        hasValue() {
            return (
                Boolean(this.field.value || this.imageUrl) &&
                !Boolean(this.deleted) &&
                !Boolean(this.missing)
            )
        },

        /**
         * Determine whether the field should show the loader component.
         */
        shouldShowLoader() {
            return !Boolean(this.deleted) && Boolean(this.imageUrl)
        },

        /**
         * Determine whether the file field input should be shown.
         */
        shouldShowField() {
            return Boolean(!this.currentlyIsReadonly)
        },

        /**
         * Determine whether the field should show the remove button.
         */
        shouldShowRemoveButton() {
            return true
        },

        /**
         * Return the preview or thumbnail URL for the field.
         */
        imageUrl() {
            return this.currentField.previewUrl || this.currentField.thumbnailUrl
        },
    },
}
</script>

<style>
.multi_title, .single_image p {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    direction: rtl;
}
</style>
