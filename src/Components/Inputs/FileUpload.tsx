import React, { Component, RefObject, createRef } from "react";
import { FileUpload as FileUploadPrime, FileUploadErrorEvent, FileUploadUploadEvent, FileUploadRemoveEvent } from 'primereact/fileupload';
import Notification from "./../Notification";
import * as uuid from 'uuid';
import { Input, InputProps, InputState } from '../Input'
import Swal from "sweetalert2";
import request from "../Request";
import { adiosError, isValidJson } from "../Helper";

interface FileUploadInputProps extends InputProps {
  uid: string,
  folderPath?: string,
  renamePattern?: string,
  multiselect?: boolean,
  allowedExtenstions?: Array<string>
}

interface UploadedFile {
  fullPath: string
}

interface FileUploadInputState extends InputState {
  endpoint: string,
  files: Array<string>
}

interface UploadResponse {
  status: string,
  message: string,
  uploadedFiles: Array<UploadedFile>
}

export default class FileUpload extends Input<FileUploadInputProps, FileUploadInputState> {
  fileUploadPrimeRef: RefObject<FileUploadPrime>;

  static defaultProps = {
    inputClassName: 'file-upload',
    id: uuid.v4(),
  }

  constructor(props: FileUploadInputProps) {
    super(props);

    let files: Array<string> = [];
    if (props.value && props.multiselect !== false) {
      if (Array.isArray(props.value)) files = props.value;
      else adiosError("Multiselect value must be type of Array");
    } else {
      if (props.value != '') files.push(props.value);
    }

    this.state = {
      ...this.state,
      files: files,
      endpoint: globalThis._APP_URL + '/components/inputs/fileupload/upload?__IS_AJAX__=1'
        + (props.folderPath ? '&folderPath=' + props.folderPath : '')
        + (props.renamePattern ? '&renamePattern=' + props.renamePattern : '')
        + (props.allowedExtenstions ? '&allowedExtenstions=' + props.allowedExtenstions : '')
    };

    this.fileUploadPrimeRef = createRef<FileUploadPrime>();
  }

  onSuccess(event: FileUploadUploadEvent) {
    let response: UploadResponse = JSON.parse(event.xhr.response);
    Notification.success(response.message);

    let uploadedFilesPaths: Array<string> = this.state.files;
    response.uploadedFiles.map((file: UploadedFile) => uploadedFilesPaths.push(file.fullPath));

    this.setState({
      files: uploadedFilesPaths
    });

    this.onChange(uploadedFilesPaths);
    this.clearUploadFiles();
  }

  onError(event: FileUploadErrorEvent) {
    let response: UploadResponse = JSON.parse(event.xhr.response);
    Notification.error(response.message);
    this.clearUploadFiles();
  }

  onDelete(fileFullPath: string) {
    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        request.delete(
          'components/inputs/fileupload/delete',
          {
            fileFullPath: fileFullPath
          },
          () => {
            Notification.success("File deleted");
            this.deleteFileFromFiles(fileFullPath);
          },
          () => {
            alert();
          }
        );
      }
    });
  }

  deleteFileFromFiles(fileFullPathToDelete: string) {
    const updatedFiles = this.state.files.filter((filePath: string) => filePath != fileFullPathToDelete);
    this.setState({
      files: updatedFiles
    });
  }

  onUploadedFileClick(fileFullPath: string) {
    Swal.fire({
      imageUrl: globalThis._APP_URL + '/upload/' + fileFullPath,
      imageAlt: 'Image',
      showConfirmButton: false
    });
  };

  clearUploadFiles() {
    setTimeout(() => {
      this.fileUploadPrimeRef.current?.clear();
    }, 100);
  }

  serialize(): string {
    if (this.props.multiselect === false) return this.state.value ? this.state.value.toString() : '';
    return JSON.stringify(this.state.files);
  }

  renderInputElement() {
    return (
      <div 
        id={"adios-title-" + this.props.uid}
        className="adios-react-ui Title p-4"
      >
        {this.state.files.length > 0 && (
          <div>
            <div className="d-flex flex-wrap">
              {this.state.files.map((fileFullPath: string, index: number) => (
                <div
                  key={index}
                  className="img-container position-relative mr-2 mb-2"
                  style={{ maxHeight: '100px', maxWidth: '100px', overflow: 'hidden' }}
                >
                  <img
                    className="img-fluid border border-gray"
                    src={globalThis._APP_URL + '/upload/' + fileFullPath}
                    alt={`Image ${index}`}
                    onClick={() => this.onUploadedFileClick(fileFullPath)}
                    style={{ height: 'auto', maxWidth: '100%', cursor: 'pointer' }}
                  />

                  <button
                    className="btn btn-sm btn-danger m-2"
                    onClick={() => this.onDelete(fileFullPath)}
                    style={{ zIndex: 9999 }}
                  >
                    <i className="fas fa-trash"></i>
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}
        <div className="card">
          <FileUploadPrime
            ref={this.fileUploadPrimeRef}
            name="upload[]"
            auto={true}
            multiple={this.props.multiselect ?? true}
            url={this.state.endpoint}
            onUpload={(event: FileUploadUploadEvent) => this.onSuccess(event)}
            onError={(event: FileUploadErrorEvent) => this.onError(event)}
            accept="image/*"
            maxFileSize={1000000}
            emptyTemplate={<p className="m-0">Drag and drop files to here to upload.</p>} />
        </div>
      </div>
    );
  }
}
