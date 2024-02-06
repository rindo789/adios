import React, { Component } from 'react'
import { WithContext as ReactTags } from 'react-tag-input';

import './../Css/Inputs/Tags.css';

interface TagsInputProps {
  parentForm: any,
  columnName: string,
  params: any
}

export default class Tags extends Component<TagsInputProps> {
  state: any;

  constructor(props: TagsInputProps) {
    super(props);

    this.state = {
      tags: [],
      suggestions: [] // Ked sa nieco zmaze omylom
    };
  }

  handleDelete = (tagIndex: string) => {
    let newTags: Array<any> = this.state.tags.filter((_, index) => index !== tagIndex);

    this.setState({
      tags: newTags
    });
  };

  handleAddition = (tag: {id: string, text: string}) => {
    this.setState({
      tags: [...this.state.tags, tag]
    });

    this.props.parentForm.inputOnChangeRaw(this.props.columnName, JSON.stringify(this.state.tags));
  };

  handleDrag = (tag: {id: string, text: string}, currPos: number, newPos: number) => {
    let newTags: Array<any> = this.state.tags.slice();

    newTags.splice(currPos, 1);
    newTags.splice(newPos, 0, tag);

    this.setState({
      tags: newTags
    });
  };

  handleTagClick = (index: number) => {
    if (this.state.tags[index].className == 'ReactTags__active')
      this.state.tags[index].className = '';
    else
      this.state.tags[index].className = "ReactTags__active";
    this.forceUpdate();

    this.props.parentForm.inputOnChange(this.props.columnName, e)
  };

  render() {

    const params = this.props.parentForm.state.inputs[this.props.columnName] ?? {all: [], values: []};

    let tags = [];
    let suggestions = params['all'];

    suggestions.forEach((role) => {
      role.id = role.name;
      if (params['values'].find((r) => r.name === role.name) !== undefined) {
        tags.push({id: role.name, name: role.name, className: "ReactTags__active"});
      } else {
        tags.push({id: role.name, name: role.name, className: ""});
      }
    });

    return (
      <ReactTags
        tags={tags}
        suggestions={suggestions}
        labelField={'name'}
        //delimiters={this.state.delimiters}
        handleDelete={this.handleDelete}
        handleAddition={this.handleAddition}
        handleDrag={this.handleDrag}
        handleTagClick={this.handleTagClick}
        inputFieldPosition="bottom"
        allowDeleteFromEmptyInput={false}
        autocomplete
      />
    );
  }
}
